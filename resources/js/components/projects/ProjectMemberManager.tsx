import { useState, useEffect } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { 
    Plus, 
    Search, 
    MoreHorizontal, 
    UserMinus, 
    Shield, 
    User,
    Crown,
    Edit3,
    Trash2
} from 'lucide-react';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import apiService from '@/services/ApiService';

interface User {
    id: string;
    name: string;
    email: string;
    avatar?: string;
}

interface ProjectMember {
    id: string;
    role: string;
    permissions: string[];
    user: User;
    added_at: string;
}

interface ProjectMemberManagerProps {
    projectId: string;
    members: ProjectMember[];
    canAdmin: boolean;
    onMembersUpdate: (members: ProjectMember[]) => void;
}

interface AvailableUser {
    id: string;
    name: string;
    email: string;
    avatar?: string;
}

export function ProjectMemberManager({ 
    projectId, 
    members, 
    canAdmin, 
    onMembersUpdate 
}: ProjectMemberManagerProps) {
    const [showAddDialog, setShowAddDialog] = useState(false);
    const [availableUsers, setAvailableUsers] = useState<AvailableUser[]>([]);
    const [searchTerm, setSearchTerm] = useState('');
    const [selectedUserId, setSelectedUserId] = useState('');
    const [selectedRole, setSelectedRole] = useState<'admin' | 'write' | 'read'>('read');
    const [loading, setLoading] = useState(false);
    const [searching, setSearching] = useState(false);

    const fetchAvailableUsers = async (search?: string) => {
        if (!canAdmin) return;
        
        setSearching(true);
        try {
            const users = await apiService.get<AvailableUser[]>(
                `/api/v1/projects/${projectId}/members/available-users`,
                { params: { search } }
            );
            setAvailableUsers(users);
        } catch (error) {
            console.error('Failed to fetch available users:', error);
        } finally {
            setSearching(false);
        }
    };

    useEffect(() => {
        if (showAddDialog) {
            fetchAvailableUsers();
        }
    }, [showAddDialog]);

    useEffect(() => {
        if (showAddDialog && searchTerm) {
            const debounced = setTimeout(() => {
                fetchAvailableUsers(searchTerm);
            }, 300);
            return () => clearTimeout(debounced);
        }
    }, [searchTerm, showAddDialog]);

    const handleAddMember = async () => {
        if (!selectedUserId || !selectedRole) return;

        setLoading(true);
        try {
            await apiService.post(`/api/v1/projects/${projectId}/members`, {
                user_id: selectedUserId,
                role: selectedRole,
            });

            // Refresh members list
            const updatedMembers = await apiService.get<ProjectMember[]>(
                `/api/v1/projects/${projectId}/members`
            );
            onMembersUpdate(updatedMembers);

            setShowAddDialog(false);
            setSelectedUserId('');
            setSelectedRole('read');
            setSearchTerm('');
        } catch (error: any) {
            console.error('Failed to add member:', error);
            if (error.response?.data?.error) {
                alert(error.response.data.error);
            }
        } finally {
            setLoading(false);
        }
    };

    const handleUpdateRole = async (memberId: string, newRole: string) => {
        setLoading(true);
        try {
            await apiService.post(`/api/v1/projects/${projectId}/members/${memberId}/update-role`, {
                role: newRole
            });

            // Refresh members list
            const updatedMembers = await apiService.get<ProjectMember[]>(
                `/api/v1/projects/${projectId}/members`
            );
            onMembersUpdate(updatedMembers);
        } catch (error: any) {
            console.error('Failed to update member role:', error);
            if (error.response?.data?.error) {
                alert(error.response.data.error);
            }
        } finally {
            setLoading(false);
        }
    };

    const handleRemoveMember = async (memberId: string) => {
        if (!confirm('Are you sure you want to remove this member?')) return;

        setLoading(true);
        try {
            await apiService.delete(`/api/v1/projects/${projectId}/members/${memberId}`);

            // Refresh members list
            const updatedMembers = await apiService.get<ProjectMember[]>(
                `/api/v1/projects/${projectId}/members`
            );
            onMembersUpdate(updatedMembers);
        } catch (error: any) {
            console.error('Failed to remove member:', error);
            if (error.response?.data?.error) {
                alert(error.response.data.error);
            }
        } finally {
            setLoading(false);
        }
    };

    const getRoleBadge = (role: string) => {
        const roleConfig = {
            admin: { label: 'Admin', variant: 'destructive' as const, icon: Crown },
            write: { label: 'Write', variant: 'default' as const, icon: Edit3 },
            read: { label: 'Read', variant: 'secondary' as const, icon: User },
        };

        const config = roleConfig[role as keyof typeof roleConfig] || roleConfig.read;
        const Icon = config.icon;

        return (
            <Badge variant={config.variant} className="flex items-center gap-1">
                <Icon className="h-3 w-3" />
                {config.label}
            </Badge>
        );
    };

    const getRoleIcon = (role: string) => {
        switch (role) {
            case 'admin': return Crown;
            case 'write': return Edit3;
            case 'read': return User;
            default: return User;
        }
    };

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <h3 className="text-lg font-medium">Project Members</h3>
                {canAdmin && (
                    <Dialog open={showAddDialog} onOpenChange={setShowAddDialog}>
                        <DialogTrigger asChild>
                            <Button size="sm">
                                <Plus className="h-4 w-4 mr-2" />
                                Add Member
                            </Button>
                        </DialogTrigger>
                        <DialogContent className="sm:max-w-md">
                            <DialogHeader>
                                <DialogTitle>Add Project Member</DialogTitle>
                                <DialogDescription>
                                    Add a new member to this project and assign their role.
                                </DialogDescription>
                            </DialogHeader>
                            <div className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="user-search">Search Users</Label>
                                    <div className="relative">
                                        <Search className="absolute left-3 top-3 h-4 w-4 text-muted-foreground" />
                                        <Input
                                            id="user-search"
                                            placeholder="Search by name or email..."
                                            value={searchTerm}
                                            onChange={(e) => setSearchTerm(e.target.value)}
                                            className="pl-10"
                                        />
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="user-select">Select User</Label>
                                    <Select value={selectedUserId} onValueChange={setSelectedUserId}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Choose a user..." />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {searching ? (
                                                <div className="p-2 text-center text-sm text-muted-foreground">
                                                    Searching...
                                                </div>
                                            ) : availableUsers.length === 0 ? (
                                                <div className="p-2 text-center text-sm text-muted-foreground">
                                                    No users found
                                                </div>
                                            ) : (
                                                availableUsers.map((user) => (
                                                    <SelectItem key={user.id} value={user.id}>
                                                        <div className="flex items-center gap-2">
                                                            <div className="w-6 h-6 rounded-full bg-primary/10 flex items-center justify-center">
                                                                <span className="text-xs font-medium">
                                                                    {user.name.charAt(0).toUpperCase()}
                                                                </span>
                                                            </div>
                                                            <div>
                                                                <div className="font-medium">{user.name}</div>
                                                                <div className="text-xs text-muted-foreground">{user.email}</div>
                                                            </div>
                                                        </div>
                                                    </SelectItem>
                                                ))
                                            )}
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="role-select">Role</Label>
                                    <Select value={selectedRole} onValueChange={(value) => setSelectedRole(value as any)}>
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="read">
                                                <div className="flex items-center gap-2">
                                                    <User className="h-4 w-4" />
                                                    <div>
                                                        <div className="font-medium">Read</div>
                                                        <div className="text-xs text-muted-foreground">Can view project</div>
                                                    </div>
                                                </div>
                                            </SelectItem>
                                            <SelectItem value="write">
                                                <div className="flex items-center gap-2">
                                                    <Edit3 className="h-4 w-4" />
                                                    <div>
                                                        <div className="font-medium">Write</div>
                                                        <div className="text-xs text-muted-foreground">Can edit items</div>
                                                    </div>
                                                </div>
                                            </SelectItem>
                                            <SelectItem value="admin">
                                                <div className="flex items-center gap-2">
                                                    <Crown className="h-4 w-4" />
                                                    <div>
                                                        <div className="font-medium">Admin</div>
                                                        <div className="text-xs text-muted-foreground">Full access</div>
                                                    </div>
                                                </div>
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                            <DialogFooter>
                                <Button 
                                    variant="outline" 
                                    onClick={() => setShowAddDialog(false)}
                                    disabled={loading}
                                >
                                    Cancel
                                </Button>
                                <Button 
                                    onClick={handleAddMember} 
                                    disabled={!selectedUserId || loading}
                                >
                                    {loading ? 'Adding...' : 'Add Member'}
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                )}
            </div>
            
            <div className="space-y-3">
                {members.map((member) => (
                    <Card key={member.id}>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    <div className="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center">
                                        <span className="text-sm font-medium">
                                            {member.user.name.charAt(0).toUpperCase()}
                                        </span>
                                    </div>
                                    <div>
                                        <p className="font-medium">{member.user.name}</p>
                                        <p className="text-sm text-muted-foreground">{member.user.email}</p>
                                    </div>
                                </div>
                                <div className="flex items-center gap-2">
                                    {getRoleBadge(member.role)}
                                    {canAdmin && (
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button variant="ghost" size="sm" className="h-8 w-8 p-0">
                                                    <MoreHorizontal className="h-4 w-4" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem 
                                                    onClick={() => handleUpdateRole(member.id, 'admin')}
                                                    disabled={member.role === 'admin'}
                                                >
                                                    <Crown className="h-4 w-4 mr-2" />
                                                    Make Admin
                                                </DropdownMenuItem>
                                                <DropdownMenuItem 
                                                    onClick={() => handleUpdateRole(member.id, 'write')}
                                                    disabled={member.role === 'write'}
                                                >
                                                    <Edit3 className="h-4 w-4 mr-2" />
                                                    Make Writer
                                                </DropdownMenuItem>
                                                <DropdownMenuItem 
                                                    onClick={() => handleUpdateRole(member.id, 'read')}
                                                    disabled={member.role === 'read'}
                                                >
                                                    <User className="h-4 w-4 mr-2" />
                                                    Make Reader
                                                </DropdownMenuItem>
                                                <DropdownMenuSeparator />
                                                <DropdownMenuItem 
                                                    onClick={() => handleRemoveMember(member.id)}
                                                    className="text-destructive"
                                                >
                                                    <Trash2 className="h-4 w-4 mr-2" />
                                                    Remove Member
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    )}
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                ))}
            </div>
        </div>
    );
}