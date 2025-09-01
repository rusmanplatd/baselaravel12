import { router } from '@inertiajs/react';
import { route } from 'ziggy-js';
import { toast } from 'sonner';
import { apiService } from '@/services/ApiService';

export interface ApiError extends Error {
    status?: number;
    response?: Response;
    data?: any;
}

export class SecurityApiError extends Error implements ApiError {
    status?: number;
    response?: Response;
    data?: any;

    constructor(message: string, status?: number, response?: Response, data?: any) {
        super(message);
        this.name = 'SecurityApiError';
        this.status = status;
        this.response = response;
        this.data = data;
    }
}

export function createApiError(response: Response, data?: any): SecurityApiError {
    let message = 'An error occurred';
    
    switch (response.status) {
        case 401:
            message = 'Authentication required. Please log in again.';
            break;
        case 403:
            message = 'You do not have permission to perform this action.';
            break;
        case 404:
            message = 'The requested resource was not found.';
            break;
        case 422:
            message = data?.message || 'Validation error. Please check your input.';
            break;
        case 429:
            message = 'Too many requests. Please wait a moment before trying again.';
            break;
        case 500:
            message = 'Internal server error. Please try again later.';
            break;
        default:
            message = data?.message || `Request failed with status ${response.status}`;
    }

    return new SecurityApiError(message, response.status, response, data);
}

export async function handleApiResponse<T = any>(response: Response): Promise<T> {
    let data: any = null;
    
    try {
        // Try to parse JSON response
        const text = await response.text();
        if (text) {
            data = JSON.parse(text);
        }
    } catch (error) {
        // Response might not be JSON, that's okay for some endpoints
        console.warn('Failed to parse response as JSON:', error);
    }

    if (!response.ok) {
        // Handle authentication errors
        if (response.status === 401 || response.status === 419) {
            toast.error('Your session has expired. Please log in again.');
            router.visit(route('login'), { replace: true });
            throw new SecurityApiError('Session expired', response.status, response, data);
        }

        // Handle other errors
        const error = createApiError(response, data);
        
        // Show user-friendly error message
        if (response.status >= 500) {
            toast.error('Server error. Please try again later.');
        } else if (response.status !== 404) {
            // Don't show toast for 404s as they might be expected
            toast.error(error.message);
        }

        throw error;
    }

    return data as T;
}

export async function securityApiCall<T = any>(
    url: string,
    options: RequestInit = {}
): Promise<T> {
    try {
        // Use apiService for the actual API call
        const method = options.method?.toLowerCase() || 'get';
        let result: T;

        switch (method) {
            case 'post':
                result = await apiService.post<T>(url, options.body ? JSON.parse(options.body as string) : undefined, options);
                break;
            case 'put':
                result = await apiService.put<T>(url, options.body ? JSON.parse(options.body as string) : undefined, options);
                break;
            case 'patch':
                result = await apiService.patch<T>(url, options.body ? JSON.parse(options.body as string) : undefined, options);
                break;
            case 'delete':
                result = await apiService.delete<T>(url, options);
                break;
            default:
                result = await apiService.get<T>(url, options);
        }

        return result;
    } catch (error: any) {
        // Convert apiService errors to SecurityApiError format
        if (error.status) {
            const securityError = createApiError({ status: error.status, ok: false } as Response, error.details);
            
            // Handle authentication errors with redirect
            if (error.status === 401 || error.status === 419) {
                toast.error('Your session has expired. Please log in again.');
                router.visit(route('login'), { replace: true });
            } else if (error.status >= 500) {
                toast.error('Server error. Please try again later.');
            } else if (error.status !== 404) {
                toast.error(securityError.message);
            }
            
            throw securityError;
        }

        // Network or other errors
        console.error('API call failed:', error);
        
        if (error instanceof TypeError && error.message.includes('fetch')) {
            toast.error('Network error. Please check your connection.');
            throw new SecurityApiError('Network error', undefined, undefined, error);
        }

        throw new SecurityApiError('Unknown error occurred', undefined, undefined, error);
    }
}

export function isNetworkError(error: unknown): boolean {
    return error instanceof TypeError && 
           (error.message.includes('fetch') || 
            error.message.includes('NetworkError') || 
            error.message.includes('Failed to fetch'));
}

export function isAuthenticationError(error: unknown): boolean {
    return error instanceof SecurityApiError && 
           (error.status === 401 || error.status === 419);
}

export function isForbiddenError(error: unknown): boolean {
    return error instanceof SecurityApiError && error.status === 403;
}

export function isValidationError(error: unknown): boolean {
    return error instanceof SecurityApiError && error.status === 422;
}