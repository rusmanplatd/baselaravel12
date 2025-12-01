const WebSocket = require('ws');
const Y = require('yjs');
const { setupWSConnection } = require('y-websocket/bin/utils');
const http = require('http');
const url = require('url');
const jwt = require('jsonwebtoken');

// Environment variables
const PORT = process.env.COLLAB_PORT || 1234;
const JWT_SECRET = process.env.JWT_SECRET || 'your-jwt-secret';
const LARAVEL_APP_URL = process.env.APP_URL || 'http://localhost:8000';

// Document storage - in production, this should be Redis or a database
const documents = new Map();

// Helper function to verify JWT token
function verifyToken(token) {
  try {
    return jwt.verify(token, JWT_SECRET);
  } catch (error) {
    console.error('JWT verification failed:', error.message);
    return null;
  }
}

// Helper function to get user info from Laravel API
async function getUserFromApi(token) {
  try {
    const response = await fetch(`${LARAVEL_APP_URL}/api/user`, {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json'
      }
    });
    
    if (response.ok) {
      return await response.json();
    }
  } catch (error) {
    console.error('Failed to fetch user from API:', error);
  }
  return null;
}

// Helper function to check document permissions
async function checkDocumentPermissions(documentId, userId, token) {
  try {
    const response = await fetch(`${LARAVEL_APP_URL}/api/documents/${documentId}/permissions`, {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json'
      }
    });
    
    if (response.ok) {
      const permissions = await response.json();
      return permissions.can_edit || permissions.can_read;
    }
  } catch (error) {
    console.error('Failed to check permissions:', error);
  }
  return false;
}

// Helper function to log collaboration session
async function logCollaborationSession(documentId, userId, sessionId, socketId, action, token) {
  try {
    await fetch(`${LARAVEL_APP_URL}/api/documents/${documentId}/collaboration-sessions`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        action,
        session_id: sessionId,
        socket_id: socketId,
        metadata: {
          timestamp: new Date().toISOString(),
          action
        }
      })
    });
  } catch (error) {
    console.error('Failed to log collaboration session:', error);
  }
}

// Helper function to update collaborator presence
async function updateCollaboratorPresence(documentId, userId, token, cursorPosition = null, selectionRange = null) {
  try {
    await fetch(`${LARAVEL_APP_URL}/api/documents/${documentId}/collaborators/${userId}/presence`, {
      method: 'PUT',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        cursor_position: cursorPosition,
        selection_range: selectionRange,
        last_seen: new Date().toISOString()
      })
    });
  } catch (error) {
    console.error('Failed to update collaborator presence:', error);
  }
}

// Create HTTP server
const server = http.createServer();

// Create WebSocket server
const wss = new WebSocket.Server({ 
  server,
  verifyClient: async (info) => {
    const query = url.parse(info.req.url, true).query;
    const token = query.token;
    const documentId = query.documentId;
    
    if (!token || !documentId) {
      console.log('Missing token or documentId in connection request');
      return false;
    }

    // Verify JWT token
    const decoded = verifyToken(token);
    if (!decoded) {
      console.log('Invalid JWT token');
      return false;
    }

    // Check document permissions
    const hasPermission = await checkDocumentPermissions(documentId, decoded.sub, token);
    if (!hasPermission) {
      console.log(`User ${decoded.sub} doesn't have permission to access document ${documentId}`);
      return false;
    }

    // Attach user info and token to the request for later use
    info.req.user = decoded;
    info.req.token = token;
    info.req.documentId = documentId;
    
    return true;
  }
});

wss.on('connection', async (ws, req) => {
  const user = req.user;
  const token = req.token;
  const documentId = req.documentId;
  const sessionId = req.headers['sec-websocket-key'] || Date.now().toString();
  const socketId = `${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;

  console.log(`User ${user.sub} connected to document ${documentId}`);

  // Log collaboration session start
  await logCollaborationSession(documentId, user.sub, sessionId, socketId, 'connect', token);

  // Update collaborator presence
  await updateCollaboratorPresence(documentId, user.sub, token);

  // Set up Yjs WebSocket connection
  const docName = `document:${documentId}`;
  
  // Custom persistence callback to save to Laravel
  const persistenceCallback = async (docName, ydoc) => {
    try {
      const update = Y.encodeStateAsUpdate(ydoc);
      const content = ydoc.getText('content').toString();
      
      // Save to Laravel API
      await fetch(`${LARAVEL_APP_URL}/api/documents/${documentId}/sync`, {
        method: 'PUT',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify({
          content,
          yjs_state: Array.from(update),
          last_edited_by: user.sub
        })
      });
    } catch (error) {
      console.error('Failed to persist document:', error);
    }
  };

  // Set up the WebSocket connection with custom persistence
  const docConnection = setupWSConnection(ws, req, {
    docName,
    gc: true,
    gcFilter: () => true,
    persistence: {
      provider: {
        retrieve: async (docName) => {
          try {
            // Load document from Laravel API
            const response = await fetch(`${LARAVEL_APP_URL}/api/documents/${documentId}`, {
              headers: {
                'Authorization': `Bearer ${token}`,
                'Accept': 'application/json'
              }
            });
            
            if (response.ok) {
              const doc = await response.json();
              if (doc.yjs_state && Array.isArray(doc.yjs_state)) {
                return new Uint8Array(doc.yjs_state);
              }
            }
          } catch (error) {
            console.error('Failed to retrieve document:', error);
          }
          return null;
        },
        persist: persistenceCallback
      }
    }
  });

  // Handle cursor and selection updates
  ws.on('message', async (message) => {
    try {
      const data = JSON.parse(message.toString());
      
      if (data.type === 'cursor-update') {
        await updateCollaboratorPresence(
          documentId, 
          user.sub, 
          token, 
          data.cursorPosition, 
          data.selectionRange
        );
        
        // Broadcast cursor position to other clients
        wss.clients.forEach((client) => {
          if (client !== ws && client.readyState === WebSocket.OPEN && 
              client.documentId === documentId) {
            client.send(JSON.stringify({
              type: 'cursor-update',
              userId: user.sub,
              userName: user.name || 'Unknown User',
              cursorPosition: data.cursorPosition,
              selectionRange: data.selectionRange
            }));
          }
        });
      }
    } catch (error) {
      // Ignore non-JSON messages (likely Yjs protocol messages)
    }
  });

  // Handle disconnection
  ws.on('close', async () => {
    console.log(`User ${user.sub} disconnected from document ${documentId}`);
    
    // Log collaboration session end
    await logCollaborationSession(documentId, user.sub, sessionId, socketId, 'disconnect', token);
    
    // Notify other clients about user leaving
    wss.clients.forEach((client) => {
      if (client !== ws && client.readyState === WebSocket.OPEN && 
          client.documentId === documentId) {
        client.send(JSON.stringify({
          type: 'user-left',
          userId: user.sub,
          userName: user.name || 'Unknown User'
        }));
      }
    });
  });

  // Store document ID and user info on the WebSocket for later reference
  ws.documentId = documentId;
  ws.userId = user.sub;
  ws.userName = user.name || 'Unknown User';

  // Send initial presence information
  const activeUsers = Array.from(wss.clients)
    .filter(client => client.documentId === documentId && client !== ws)
    .map(client => ({
      userId: client.userId,
      userName: client.userName
    }));

  if (activeUsers.length > 0) {
    ws.send(JSON.stringify({
      type: 'active-users',
      users: activeUsers
    }));
  }

  // Notify other clients about new user joining
  wss.clients.forEach((client) => {
    if (client !== ws && client.readyState === WebSocket.OPEN && 
        client.documentId === documentId) {
      client.send(JSON.stringify({
        type: 'user-joined',
        userId: user.sub,
        userName: user.name || 'Unknown User'
      }));
    }
  });
});

// Health check endpoint
server.on('request', (req, res) => {
  if (req.url === '/health') {
    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({
      status: 'healthy',
      timestamp: new Date().toISOString(),
      activeConnections: wss.clients.size
    }));
    return;
  }
  
  res.writeHead(404);
  res.end('Not Found');
});

// Start the server
server.listen(PORT, () => {
  console.log(`🚀 Collaboration server running on port ${PORT}`);
  console.log(`📊 Health check available at http://localhost:${PORT}/health`);
});

// Graceful shutdown
process.on('SIGTERM', () => {
  console.log('Shutting down collaboration server...');
  wss.clients.forEach(ws => {
    ws.terminate();
  });
  server.close(() => {
    console.log('Collaboration server shut down');
    process.exit(0);
  });
});

process.on('SIGINT', () => {
  console.log('Shutting down collaboration server...');
  wss.clients.forEach(ws => {
    ws.terminate();
  });
  server.close(() => {
    console.log('Collaboration server shut down');
    process.exit(0);
  });
});