import { useState, useEffect } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import { Separator } from '@/components/ui/separator';
import { 
    Shield, 
    UserPlus, 
    Crown, 
    Wrench, 
    Edit, 
    Eye, 
    Users, 
    Trash2,
    Settings,
    CheckCircle2,
    AlertTriangle
} from 'lucide-react';
import apiService from '@/services/ApiService';

interface User {
    id: string;
    name: string;
    email: string;
    avatar?: string;
}

interface ProjectMember {
    id: string;
    user: User;
    role: 'project.admin' | 'project.maintainer' | 'project.editor' | 'project.contributor' | 'project.viewer';
    permissions: string[];
    added_at: string;
    added_by: User;
}

interface Permission {
    name: string;
    description: string;
    category: string;
}

interface Role {
    name: string;
    display_name: string;
    description: string;
    permissions: string[];
    color: string;
    icon: string;
}

interface ProjectPermissionManagerProps {
    projectId: string;
    members: ProjectMember[];
    currentUser: User;
    canAdmin: boolean;
    onMembersUpdate: (members: ProjectMember[]) => void;
}

const PROJECT_ROLES: Role[] = [
    {
        name: 'project.admin',
        display_name: 'Admin',
        description: 'Full control over project settings, members, and all content',
        permissions: [],
        color: 'bg-red-100 text-red-800 border-red-200',
        icon: 'Crown'
    },
    {
        name: 'project.maintainer',
        display_name: 'Maintainer',
        description: 'Can manage items, views, workflows and invite members',
        permissions: [],
        color: 'bg-blue-100 text-blue-800 border-blue-200',
        icon: 'Wrench'
    },
    {
        name: 'project.editor',
        display_name: 'Editor',
        description: 'Can create and edit items, views, and trigger workflows',
        permissions: [],
        color: 'bg-green-100 text-green-800 border-green-200',
        icon: 'Edit'
    },
    {
        name: 'project.contributor',
        display_name: 'Contributor',
        description: 'Can create and edit items, limited view creation',
        permissions: [],
        color: 'bg-yellow-100 text-yellow-800 border-yellow-200',
        icon: 'Users'
    },
    {
        name: 'project.viewer',
        display_name: 'Viewer',
        description: 'Read-only access to project and members',
        permissions: [],
        color: 'bg-gray-100 text-gray-800 border-gray-200',
        icon: 'Eye'
    }
];

export function ProjectPermissionManager({ 
    projectId, 
    members, 
    currentUser, 
    canAdmin, 
    onMembersUpdate 
}: ProjectPermissionManagerProps) {
    const [showInviteDialog, setShowInviteDialog] = useState(false);
    const [availableUsers, setAvailableUsers] = useState<User[]>([]);
    const [inviteForm, setInviteForm] = useState({
        user_id: '',
        role: 'project.viewer' as const,
        custom_permissions: [] as string[]
    });
    const [loading, setLoading] = useState(false);
    const [permissions, setPermissions] = useState<Permission[]>([]);

    useEffect(() => {
        if (showInviteDialog) {
            fetchAvailableUsers();
            fetchPermissions();
        }
    }, [showInviteDialog]);

    const fetchAvailableUsers = async () => {
        try {
            const response = await apiService.get<{ data: User[] }>(`/api/v1/projects/${projectId}/members/available-users`);
            setAvailableUsers(response.data);
        } catch (error) {
            console.error('Failed to fetch available users:', error);
        }
    };

    const fetchPermissions = async () => {
        try {
            const response = await apiService.get<{ data: Permission[] }>('/api/v1/permissions?filter=project');
            setPermissions(response.data);
        } catch (error) {
            console.error('Failed to fetch permissions:', error);
        }
    };

    const inviteMember = async () => {
        try {
            setLoading(true);
            const response = await apiService.post<{ data: ProjectMember }>(`/api/v1/projects/${projectId}/members`, inviteForm);
            onMembersUpdate([...members, response.data]);
            setShowInviteDialog(false);
            setInviteForm({ user_id: '', role: 'project.viewer', custom_permissions: [] });
        } catch (error) {
            console.error('Failed to invite member:', error);
        } finally {
            setLoading(false);
        }
    };

    const updateMemberRole = async (memberId: string, newRole: string) => {
        try {
            const response = await apiService.post<{ data: ProjectMember }>(`/api/v1/projects/${projectId}/members/${memberId}/update-role`, {
                role: newRole
            });
            onMembersUpdate(members.map(member => 
                member.id === memberId ? response.data : member
            ));
        } catch (error) {
            console.error('Failed to update member role:', error);
        }
    };

    const removeMember = async (memberId: string) => {
        if (!confirm('Are you sure you want to remove this member from the project?')) return;

        try {
            await apiService.delete(`/api/v1/projects/${projectId}/members/${memberId}`);
            onMembersUpdate(members.filter(member => member.id !== memberId));
        } catch (error) {
            console.error('Failed to remove member:', error);
        }
    };

    const getRoleInfo = (roleName: string) => {
        return PROJECT_ROLES.find(role => role.name === roleName) || PROJECT_ROLES[4];
    };

    const getRoleIcon = (roleName: string) => {
        const role = getRoleInfo(roleName);
        const icons = {
            Crown: Crown,
            Wrench: Wrench,
            Edit: Edit,
            Users: Users,
            Eye: Eye
        };
        const IconComponent = icons[role.icon as keyof typeof icons] || Eye;
        return <IconComponent className="h-3 w-3" />;
    };

    const getRoleBadge = (roleName: string) => {
        const role = getRoleInfo(roleName);
        return (
            <Badge variant="outline" className={`flex items-center gap-1 ${role.color}`}>
                {getRoleIcon(roleName)}
                {role.display_name}
            </Badge>
        );
    };

    const groupPermissionsByCategory = (permissions: Permission[]) => {
        return permissions.reduce((acc, permission) => {
            const category = permission.category || 'Other';
            if (!acc[category]) {
                acc[category] = [];
            }
            acc[category].push(permission);
            return acc;
        }, {} as Record<string, Permission[]>);
    };

    const toggleCustomPermission = (permissionName: string) => {
        setInviteForm(prev => ({
            ...prev,
            custom_permissions: prev.custom_permissions.includes(permissionName)
                ? prev.custom_permissions.filter(p => p !== permissionName)
                : [...prev.custom_permissions, permissionName]
        }));
    };

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <div>
                    <h3 className="text-lg font-semibold">Members & Permissions</h3>
                    <p className="text-sm text-muted-foreground">
                        Manage who can access and modify this project
                    </p>
                </div>
                {canAdmin && (
                    <Dialog open={showInviteDialog} onOpenChange={setShowInviteDialog}>
                        <DialogTrigger asChild>
                            <Button>
                                <UserPlus className="h-4 w-4 mr-2" />
                                Invite Member
                            </Button>
                        </DialogTrigger>
                        <DialogContent className="sm:max-w-lg max-h-[80vh] overflow-y-auto">
                            <DialogHeader>
                                <DialogTitle>Invite Team Member</DialogTitle>
                                <DialogDescription>
                                    Add a new member to this project with specific permissions.
                                </DialogDescription>
                            </DialogHeader>
                            <div className="space-y-4">
                                <div>
                                    <Label htmlFor="user">Select User</Label>
                                    <Select value={inviteForm.user_id} onValueChange={(value) => 
                                        setInviteForm(prev => ({ ...prev, user_id: value }))
                                    }>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Choose a user to invite" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {availableUsers.map((user) => (
                                                <SelectItem key={user.id} value={user.id}>
                                                    <div className="flex items-center gap-2">
                                                        <Avatar className="h-4 w-4">
                                                            <AvatarImage src={user.avatar} alt={user.name} />
                                                            <AvatarFallback className="text-xs">
                                                                {user.name.charAt(0)}
                                                            </AvatarFallback>
                                                        </Avatar>
                                                        <div>
                                                            <div className="font-medium text-sm">{user.name}</div>
                                                            <div className="text-xs text-muted-foreground">{user.email}</div>
                                                        </div>
                                                    </div>
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div>
                                    <Label>Project Role</Label>
                                    <div className="grid gap-2 mt-2">
                                        {PROJECT_ROLES.map((role) => (
                                            <div key={role.name} className="flex items-start space-x-3">
                                                <input
                                                    type="radio"
                                                    id={role.name}
                                                    name="role"
                                                    value={role.name}
                                                    checked={inviteForm.role === role.name}
                                                    onChange={(e) => setInviteForm(prev => ({ 
                                                        ...prev, 
                                                        role: e.target.value as any 
                                                    }))}
                                                    className="mt-0.5"
                                                />
                                                <div className="flex-1">
                                                    <div className="flex items-center gap-2 mb-1">
                                                        <Label htmlFor={role.name} className="font-medium cursor-pointer">
                                                            {role.display_name}
                                                        </Label>
                                                        {getRoleBadge(role.name)}
                                                    </div>
                                                    <p className="text-xs text-muted-foreground">
                                                        {role.description}
                                                    </p>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>

                                {permissions.length > 0 && inviteForm.role !== 'project.admin' && (
                                    <div>
                                        <Label>Additional Permissions (Optional)</Label>
                                        <div className="mt-2 space-y-3 max-h-40 overflow-y-auto border rounded-md p-3">
                                            {Object.entries(groupPermissionsByCategory(permissions)).map(([category, categoryPermissions]) => (
                                                <div key={category}>
                                                    <h4 className="text-sm font-medium text-muted-foreground mb-2">{category}</h4>
                                                    <div className="space-y-2 ml-2">
                                                        {categoryPermissions.map((permission) => (
                                                            <div key={permission.name} className="flex items-start space-x-2">
                                                                <Checkbox
                                                                    id={permission.name}
                                                                    checked={inviteForm.custom_permissions.includes(permission.name)}
                                                                    onCheckedChange={() => toggleCustomPermission(permission.name)}
                                                                />
                                                                <div>
                                                                    <Label htmlFor={permission.name} className="text-sm cursor-pointer">
                                                                        {permission.description || permission.name}
                                                                    </Label>
                                                                </div>
                                                            </div>
                                                        ))}
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}

                                <div className="flex gap-2 justify-end">
                                    <Button variant="outline" onClick={() => setShowInviteDialog(false)}>
                                        Cancel
                                    </Button>
                                    <Button 
                                        onClick={inviteMember} 
                                        disabled={!inviteForm.user_id || loading}
                                    >
                                        {loading ? 'Inviting...' : 'Invite Member'}
                                    </Button>
                                </div>
                            </div>
                        </DialogContent>
                    </Dialog>
                )}
            </div>

            {/* Members List */}
            <div className="space-y-3">
                {members.map((member) => {
                    const isCurrentUser = member.user.id === currentUser.id;
                    const canManage = canAdmin && !isCurrentUser;

                    return (
                        <Card key={member.id}>
                            <CardContent className="p-4">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-3">
                                        <Avatar className="h-10 w-10">
                                            <AvatarImage src={member.user.avatar} alt={member.user.name} />
                                            <AvatarFallback>
                                                {member.user.name.charAt(0)}
                                            </AvatarFallback>
                                        </Avatar>
                                        <div className="flex-1">
                                            <div className="flex items-center gap-2">
                                                <h4 className="font-medium">{member.user.name}</h4>
                                                {isCurrentUser && (
                                                    <Badge variant="outline" className="text-xs">You</Badge>
                                                )}
                                            </div>
                                            <p className="text-sm text-muted-foreground">{member.user.email}</p>
                                            <div className="flex items-center gap-2 mt-1">
                                                {getRoleBadge(member.role)}
                                                {member.permissions.length > 0 && (
                                                    <Badge variant="outline" className="text-xs">
                                                        <Shield className="h-2 w-2 mr-1" />
                                                        +{member.permissions.length} permissions
                                                    </Badge>
                                                )}
                                            </div>
                                        </div>
                                    </div>

                                    {canManage && (
                                        <div className="flex items-center gap-2">
                                            <Select 
                                                value={member.role} 
                                                onValueChange={(value) => updateMemberRole(member.id, value)}
                                            >
                                                <SelectTrigger className="w-40">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {PROJECT_ROLES.map((role) => (
                                                        <SelectItem key={role.name} value={role.name}>
                                                            {role.display_name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => removeMember(member.id)}
                                                className="text-destructive hover:text-destructive"
                                            >
                                                <Trash2 className="h-3 w-3" />
                                            </Button>
                                        </div>
                                    )}
                                </div>

                                <div className="mt-3 text-xs text-muted-foreground">
                                    Added by {member.added_by.name} on {new Date(member.added_at).toLocaleDateString()}
                                </div>
                            </CardContent>
                        </Card>
                    );
                })}
            </div>

            {/* Role Permissions Reference */}
            <Card>
                <CardHeader>
                    <CardTitle className="text-base flex items-center gap-2">
                        <Settings className="h-4 w-4" />
                        Role Permissions Reference
                    </CardTitle>
                    <CardDescription>
                        Understanding what each role can do in this project
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    {PROJECT_ROLES.map((role) => (
                        <div key={role.name} className="flex items-start gap-3">
                            <div className="pt-1">
                                {getRoleBadge(role.name)}
                            </div>
                            <div className="flex-1">
                                <p className="text-sm">{role.description}</p>
                            </div>
                        </div>
                    ))}
                </CardContent>
            </Card>
        </div>
    );
}