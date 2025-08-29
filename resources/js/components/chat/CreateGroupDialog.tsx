import React, { useState } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { UserSearchCombobox } from '@/components/ui/user-search-combobox';
import { toast } from 'sonner';
import { User } from '@/types';
import { apiService, ApiError } from '@/services/ApiService';
import { 
  UserGroupIcon, 
  PlusIcon,
  XMarkIcon
} from '@heroicons/react/24/outline';

interface CreateGroupDialogProps {
  onCreateGroup: (groupData: {
    name: string;
    description?: string;
    participants: string[];
  }) => Promise<void>;
  trigger?: React.ReactNode;
}

export default function CreateGroupDialog({ onCreateGroup, trigger }: CreateGroupDialogProps) {
  const [open, setOpen] = useState(false);
  const [loading, setLoading] = useState(false);
  const [step, setStep] = useState<'details' | 'members'>('details');
  
  // Group details
  const [groupName, setGroupName] = useState('');
  const [description, setDescription] = useState('');
  
  // Member selection
  const [currentSearchValue, setCurrentSearchValue] = useState('');
  const [selectedMembers, setSelectedMembers] = useState<Array<{ id: string; name: string; email: string }>>([]);

  const resetForm = () => {
    setStep('details');
    setGroupName('');
    setDescription('');
    setCurrentSearchValue('');
    setSelectedMembers([]);
  };

  const handleNext = () => {
    if (step === 'details') {
      if (!groupName.trim()) {
        toast.error('Group name is required');
        return;
      }
      setStep('members');
    }
  };

  const handleBack = () => {
    setStep('details');
  };

  const handleUserSelect = async (email: string) => {
    // Check if user is already selected
    if (selectedMembers.find(m => m.email === email)) {
      toast.error('User already added to group');
      return;
    }

    // Fetch user details from API using the email
    try {
      const userData = await apiService.get<User>(`/api/v1/users/by-email?email=${encodeURIComponent(email)}`);
      
      // Add the user to selected members
      setSelectedMembers([...selectedMembers, {
        id: userData.id.toString(),
        name: userData.name,
        email: userData.email
      }]);
      
      // Clear the search
      setCurrentSearchValue('');
      
      toast.success(`${userData.name} added to group`);
    } catch (error) {
      console.error('Error fetching user details:', error);
      // Fallback: add with just the email if API fails
      setSelectedMembers([...selectedMembers, {
        id: Date.now().toString(), // temporary ID
        name: email.split('@')[0], // use email username as name
        email: email
      }]);
      setCurrentSearchValue('');
      toast.success('User added to group');
    }
  };

  const removeMember = (userId: string) => {
    setSelectedMembers(selectedMembers.filter(m => m.id !== userId));
  };

  const handleCreateGroup = async () => {
    if (!groupName.trim()) {
      toast.error('Group name is required');
      return;
    }

    if (selectedMembers.length === 0) {
      toast.error('At least one member is required');
      return;
    }

    setLoading(true);
    try {
      await onCreateGroup({
        name: groupName,
        description: description || undefined,
        participants: selectedMembers.map(m => m.email) // Use email for participant identification
      });
      
      toast.success('Group created successfully!');
      setOpen(false);
      resetForm();
    } catch (error: any) {
      toast.error(error.message || 'Failed to create group');
    } finally {
      setLoading(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={(newOpen) => {
      setOpen(newOpen);
      if (!newOpen) resetForm();
    }}>
      <DialogTrigger asChild>
        {trigger || (
          <Button>
            <PlusIcon className="h-4 w-4 mr-2" />
            Create Group
          </Button>
        )}
      </DialogTrigger>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle className="flex items-center space-x-2">
            <UserGroupIcon className="h-5 w-5" />
            <span>Create New Group</span>
          </DialogTitle>
        </DialogHeader>

        {step === 'details' && (
          <div className="space-y-4">
            <div>
              <Label htmlFor="group-name">Group Name *</Label>
              <Input
                id="group-name"
                value={groupName}
                onChange={(e) => setGroupName(e.target.value)}
                placeholder="Enter group name..."
                className="mt-1"
                autoFocus
              />
            </div>

            <div>
              <Label htmlFor="group-description">Description</Label>
              <Textarea
                id="group-description"
                value={description}
                onChange={(e) => setDescription(e.target.value)}
                placeholder="Enter group description..."
                className="mt-1"
                rows={3}
              />
            </div>

            <div className="flex justify-end space-x-2">
              <Button variant="outline" onClick={() => setOpen(false)}>
                Cancel
              </Button>
              <Button onClick={handleNext}>
                Next: Add Members
              </Button>
            </div>
          </div>
        )}

        {step === 'members' && (
          <div className="space-y-4">
            <div className="space-y-2">
              <Label>Search and add members</Label>
              <UserSearchCombobox
                value={currentSearchValue}
                onSelect={handleUserSelect}
                placeholder="Search by name or email..."
              />
            </div>

            {/* Selected Members */}
            {selectedMembers.length > 0 && (
              <div className="space-y-2">
                <Label>Selected Members ({selectedMembers.length})</Label>
                <div className="space-y-2 max-h-32 overflow-y-auto">
                  {selectedMembers.map((member) => (
                    <div key={member.id} className="flex items-center justify-between p-2 bg-blue-50 rounded">
                      <div className="flex items-center space-x-2">
                        <Avatar className="h-6 w-6">
                          <AvatarFallback className="text-xs">
                            {member.name.charAt(0).toUpperCase()}
                          </AvatarFallback>
                        </Avatar>
                        <div>
                          <p className="text-sm font-medium">{member.name}</p>
                          <p className="text-xs text-gray-500">{member.email}</p>
                        </div>
                      </div>
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => removeMember(member.id)}
                        className="h-6 w-6 p-0 text-gray-400 hover:text-red-600"
                      >
                        <XMarkIcon className="h-4 w-4" />
                      </Button>
                    </div>
                  ))}
                </div>
              </div>
            )}

            <div className="flex justify-between space-x-2">
              <Button variant="outline" onClick={handleBack}>
                Back
              </Button>
              <Button 
                onClick={handleCreateGroup} 
                disabled={loading || selectedMembers.length === 0}
              >
                {loading ? 'Creating...' : `Create Group (${selectedMembers.length} members)`}
              </Button>
            </div>
          </div>
        )}
      </DialogContent>
    </Dialog>
  );
}