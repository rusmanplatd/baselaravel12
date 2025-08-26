/**
 * Helper functions for creating and managing test users in E2E tests
 */

interface TestUser {
  name: string;
  email: string;
  password: string;
}

interface ApiResponse {
  success: boolean;
  user?: any;
  error?: string;
}

export class TestUserHelper {
  /**
   * Create a verified user via API for testing
   */
  static async createVerifiedUser(baseURL: string, user: TestUser): Promise<ApiResponse> {
    try {
      // First, get CSRF token
      const csrfResponse = await fetch(`${baseURL}/`, {
        credentials: 'include',
      });
      
      if (!csrfResponse.ok) {
        return { success: false, error: 'Failed to get CSRF token' };
      }
      
      const csrfHtml = await csrfResponse.text();
      const csrfMatch = csrfHtml.match(/name="csrf-token"\s+content="([^"]+)"/);
      const csrfToken = csrfMatch ? csrfMatch[1] : '';
      
      if (!csrfToken) {
        return { success: false, error: 'CSRF token not found' };
      }

      // Create user via registration API
      const registerResponse = await fetch(`${baseURL}/register`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
        },
        credentials: 'include',
        body: JSON.stringify({
          name: user.name,
          email: user.email,
          password: user.password,
          password_confirmation: user.password,
        }),
      });

      if (!registerResponse.ok) {
        const errorData = await registerResponse.text();
        return { success: false, error: `Registration failed: ${errorData}` };
      }

      return { success: true };
    } catch (error) {
      return { success: false, error: `API error: ${error}` };
    }
  }

  /**
   * Login user via API
   */
  static async loginUser(baseURL: string, email: string, password: string): Promise<ApiResponse> {
    try {
      // Get CSRF token
      const csrfResponse = await fetch(`${baseURL}/login`, {
        credentials: 'include',
      });
      
      if (!csrfResponse.ok) {
        return { success: false, error: 'Failed to get login page' };
      }
      
      const csrfHtml = await csrfResponse.text();
      const csrfMatch = csrfHtml.match(/name="csrf-token"\s+content="([^"]+)"/);
      const csrfToken = csrfMatch ? csrfMatch[1] : '';

      // Login
      const loginResponse = await fetch(`${baseURL}/login`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
        },
        credentials: 'include',
        body: JSON.stringify({
          email,
          password,
        }),
      });

      if (!loginResponse.ok) {
        const errorData = await loginResponse.text();
        return { success: false, error: `Login failed: ${errorData}` };
      }

      return { success: true };
    } catch (error) {
      return { success: false, error: `Login error: ${error}` };
    }
  }

  /**
   * Clean up test users (if needed)
   */
  static async cleanupTestUsers(baseURL: string, emails: string[]): Promise<void> {
    // Implementation would depend on having a cleanup API endpoint
    // For now, this is a placeholder
    console.log(`Would cleanup test users: ${emails.join(', ')}`);
  }
}