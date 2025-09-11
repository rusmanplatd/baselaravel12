import { useState, useEffect } from 'react';
import apiService from '@/services/ApiService';

interface ProjectIteration {
    id: string;
    title: string;
    description?: string;
    start_date: string;
    end_date: string;
    status: 'planned' | 'active' | 'completed' | 'cancelled';
    duration_weeks: number;
    goals?: string[];
    creator: {
        id: string;
        name: string;
        email: string;
    };
    items_count: number;
    completion_stats: {
        total: number;
        completed: number;
        percentage: number;
    };
    time_stats: {
        duration_days: number;
        remaining_days: number;
        progress_percentage: number;
    };
    created_at: string;
    updated_at: string;
}

interface CreateIterationData {
    title: string;
    description?: string;
    start_date: string;
    end_date: string;
    goals?: string[];
    duration_weeks?: number;
}

interface UseProjectIterationsReturn {
    iterations: ProjectIteration[];
    currentIteration: ProjectIteration | null;
    loading: boolean;
    error: string | null;
    createIteration: (data: CreateIterationData) => Promise<ProjectIteration | null>;
    updateIteration: (id: string, data: Partial<CreateIterationData>) => Promise<ProjectIteration | null>;
    deleteIteration: (id: string) => Promise<boolean>;
    startIteration: (id: string) => Promise<ProjectIteration | null>;
    completeIteration: (id: string) => Promise<ProjectIteration | null>;
    cancelIteration: (id: string) => Promise<ProjectIteration | null>;
    addItemsToIteration: (iterationId: string, itemIds: string[]) => Promise<boolean>;
    removeItemsFromIteration: (iterationId: string, itemIds: string[]) => Promise<boolean>;
    refresh: () => Promise<void>;
}

/**
 * Hook for managing project iterations (sprints)
 */
export function useProjectIterations(projectId: string): UseProjectIterationsReturn {
    const [iterations, setIterations] = useState<ProjectIteration[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const fetchIterations = async () => {
        if (!projectId) return;

        try {
            setLoading(true);
            setError(null);
            
            const response = await apiService.get<{
                data: ProjectIteration[];
                meta: {
                    total: number;
                    current_iteration: ProjectIteration | null;
                };
            }>(`/api/v1/projects/${projectId}/iterations`);

            setIterations(response.data);
        } catch (err: any) {
            console.error('Failed to fetch iterations:', err);
            setError(err.message || 'Failed to load iterations');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchIterations();
    }, [projectId]);

    const createIteration = async (data: CreateIterationData): Promise<ProjectIteration | null> => {
        try {
            const response = await apiService.post<{ data: ProjectIteration }>(
                `/api/v1/projects/${projectId}/iterations`,
                data
            );
            
            const newIteration = response.data;
            setIterations(prev => [newIteration, ...prev]);
            return newIteration;
        } catch (err: any) {
            console.error('Failed to create iteration:', err);
            setError(err.message || 'Failed to create iteration');
            return null;
        }
    };

    const updateIteration = async (id: string, data: Partial<CreateIterationData>): Promise<ProjectIteration | null> => {
        try {
            const response = await apiService.put<{ data: ProjectIteration }>(
                `/api/v1/projects/${projectId}/iterations/${id}`,
                data
            );
            
            const updatedIteration = response.data;
            setIterations(prev => prev.map(iteration => 
                iteration.id === id ? updatedIteration : iteration
            ));
            return updatedIteration;
        } catch (err: any) {
            console.error('Failed to update iteration:', err);
            setError(err.message || 'Failed to update iteration');
            return null;
        }
    };

    const deleteIteration = async (id: string): Promise<boolean> => {
        try {
            await apiService.delete(`/api/v1/projects/${projectId}/iterations/${id}`);
            setIterations(prev => prev.filter(iteration => iteration.id !== id));
            return true;
        } catch (err: any) {
            console.error('Failed to delete iteration:', err);
            setError(err.message || 'Failed to delete iteration');
            return false;
        }
    };

    const startIteration = async (id: string): Promise<ProjectIteration | null> => {
        try {
            const response = await apiService.post<{ data: ProjectIteration }>(
                `/api/v1/projects/${projectId}/iterations/${id}/start`,
                {}
            );
            
            const updatedIteration = response.data;
            setIterations(prev => prev.map(iteration => 
                iteration.id === id ? updatedIteration : iteration
            ));
            return updatedIteration;
        } catch (err: any) {
            console.error('Failed to start iteration:', err);
            setError(err.message || 'Failed to start iteration');
            return null;
        }
    };

    const completeIteration = async (id: string): Promise<ProjectIteration | null> => {
        try {
            const response = await apiService.post<{ data: ProjectIteration }>(
                `/api/v1/projects/${projectId}/iterations/${id}/complete`,
                {}
            );
            
            const updatedIteration = response.data;
            setIterations(prev => prev.map(iteration => 
                iteration.id === id ? updatedIteration : iteration
            ));
            return updatedIteration;
        } catch (err: any) {
            console.error('Failed to complete iteration:', err);
            setError(err.message || 'Failed to complete iteration');
            return null;
        }
    };

    const cancelIteration = async (id: string): Promise<ProjectIteration | null> => {
        try {
            const response = await apiService.post<{ data: ProjectIteration }>(
                `/api/v1/projects/${projectId}/iterations/${id}/cancel`,
                {}
            );
            
            const updatedIteration = response.data;
            setIterations(prev => prev.map(iteration => 
                iteration.id === id ? updatedIteration : iteration
            ));
            return updatedIteration;
        } catch (err: any) {
            console.error('Failed to cancel iteration:', err);
            setError(err.message || 'Failed to cancel iteration');
            return null;
        }
    };

    const addItemsToIteration = async (iterationId: string, itemIds: string[]): Promise<boolean> => {
        try {
            await apiService.post(
                `/api/v1/projects/${projectId}/iterations/${iterationId}/items`,
                { item_ids: itemIds }
            );
            
            // Refresh iterations to get updated item counts
            await fetchIterations();
            return true;
        } catch (err: any) {
            console.error('Failed to add items to iteration:', err);
            setError(err.message || 'Failed to add items to iteration');
            return false;
        }
    };

    const removeItemsFromIteration = async (iterationId: string, itemIds: string[]): Promise<boolean> => {
        try {
            await apiService.delete(
                `/api/v1/projects/${projectId}/iterations/${iterationId}/items`,
                { data: { item_ids: itemIds } }
            );
            
            // Refresh iterations to get updated item counts
            await fetchIterations();
            return true;
        } catch (err: any) {
            console.error('Failed to remove items from iteration:', err);
            setError(err.message || 'Failed to remove items from iteration');
            return false;
        }
    };

    const refresh = async () => {
        await fetchIterations();
    };

    // Get current active iteration
    const currentIteration = iterations.find(iteration => iteration.status === 'active') || null;

    return {
        iterations,
        currentIteration,
        loading,
        error,
        createIteration,
        updateIteration,
        deleteIteration,
        startIteration,
        completeIteration,
        cancelIteration,
        addItemsToIteration,
        removeItemsFromIteration,
        refresh,
    };
}