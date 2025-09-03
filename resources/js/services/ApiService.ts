/**
 * Centralized API Service for handling authentication and HTTP requests
 * Uses Laravel Passport for API authentication
 */

class ApiService {
    private static instance: ApiService;
    private accessToken: string | null = null;
    private readonly STORAGE_KEY = 'api_token';
    private isRefreshing = false;
    private refreshPromise: Promise<string> | null = null;

    private constructor() {
        // Load token from storage on initialization
        this.loadTokenFromStorage();
    }

    public static getInstance(): ApiService {
        if (!ApiService.instance) {
            ApiService.instance = new ApiService();
        }
        return ApiService.instance;
    }

    /**
     * Load token from localStorage
     */
    private loadTokenFromStorage(): void {
        try {
            const token = localStorage.getItem(this.STORAGE_KEY);
            if (token) {
                this.accessToken = token;
            }
        } catch (error) {
            console.error('Failed to load token from storage:', error);
        }
    }

    /**
     * Save token to localStorage
     */
    private saveTokenToStorage(token: string): void {
        try {
            localStorage.setItem(this.STORAGE_KEY, token);
            this.accessToken = token;
        } catch (error) {
            console.error('Failed to save token to storage:', error);
        }
    }

    /**
     * Clear stored token
     */
    private clearTokenFromStorage(): void {
        try {
            localStorage.removeItem(this.STORAGE_KEY);
            this.accessToken = null;
        } catch (error) {
            console.error('Failed to clear token from storage:', error);
        }
    }

    /**
     * Get CSRF token from meta tag
     */
    private getCSRFToken(): string {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    /**
     * Generate a new API token
     */
    private async generateNewToken(): Promise<string> {
        if (this.isRefreshing && this.refreshPromise) {
            return this.refreshPromise;
        }

        this.isRefreshing = true;
        this.refreshPromise = this.performTokenGeneration();

        try {
            const token = await this.refreshPromise;
            return token;
        } finally {
            this.isRefreshing = false;
            this.refreshPromise = null;
        }
    }

    /**
     * Perform the actual token generation
     */
    private async performTokenGeneration(): Promise<string> {
        const response = await fetch('/api/generate-token', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': this.getCSRFToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (response.status === 401 || response.status === 403) {
            throw new ApiError('Authentication required. Please log in to access the application.', response.status);
        }

        if (!response.ok) {
            const errorText = await response.text();
            throw new ApiError(`Failed to generate API token: ${response.statusText}`, response.status, errorText);
        }

        const data = await response.json() as { access_token?: string };
        const token = data.access_token;

        if (!token) {
            throw new ApiError('No access token received from server', 500);
        }

        this.saveTokenToStorage(token);
        return token;
    }

    /**
     * Get current access token, generating one if needed
     */
    public async getAccessToken(): Promise<string> {
        if (!this.accessToken) {
            return this.generateNewToken();
        }
        return this.accessToken;
    }

    /**
     * Get headers for API requests
     */
    public async getHeaders(additionalHeaders: Record<string, string> = {}): Promise<Record<string, string>> {
        const token = await this.getAccessToken();

        return {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...additionalHeaders,
        };
    }

    /**
     * Handle API response with error handling and token refresh
     */
    private async handleResponse<T>(response: Response): Promise<T> {
        if (response.status === 401) {
            // Token is expired or invalid, clear it and throw auth error
            this.clearTokenFromStorage();
            throw new ApiError('Authentication expired. Please refresh the page and log in again.', 401);
        }

        if (!response.ok) {
            let errorMessage = `API request failed: ${response.status} ${response.statusText}`;
            let errorDetails: unknown;

            try {
                errorDetails = await response.json();
                if (errorDetails && typeof errorDetails === 'object' && 'message' in errorDetails) {
                    errorMessage = String(errorDetails.message);
                }
            } catch {
                // If JSON parsing fails, use the status text
                errorDetails = await response.text();
            }

            throw new ApiError(errorMessage, response.status, errorDetails);
        }

        // Handle empty responses
        if (response.status === 204) {
            return {} as T;
        }

        try {
            return await response.json() as T;
        } catch (error) {
            throw new ApiError('Invalid JSON response from server', 500, error);
        }
    }

    /**
     * Make a GET request
     */
    public async get<T>(url: string, options: RequestInit = {}): Promise<T> {
        const headers = await this.getHeaders();

        const response = await fetch(url, {
            method: 'GET',
            headers: { ...headers, ...options.headers },
        });

        return this.handleResponse<T>(response);
    }

    /**
     * Make a POST request
     */
    public async post<T>(url: string, data?: unknown, options: RequestInit = {}): Promise<T> {
        const headers = await this.getHeaders();

        console.log('POST headers:', headers);
        const response = await fetch(url, {
            method: 'POST',
            headers: { ...headers, ...options.headers },
            body: data ? JSON.stringify(data) : undefined,
        });

        return this.handleResponse<T>(response);
    }

    /**
     * Make a PUT request
     */
    public async put<T>(url: string, data?: unknown, options: RequestInit = {}): Promise<T> {
        const headers = await this.getHeaders();

        const response = await fetch(url, {
            method: 'PUT',
            headers: { ...headers, ...options.headers },
            body: data ? JSON.stringify(data) : undefined,
        });

        return this.handleResponse<T>(response);
    }

    /**
     * Make a DELETE request
     */
    public async delete<T>(url: string, options: RequestInit = {}): Promise<T> {
        const headers = await this.getHeaders();

        const response = await fetch(url, {
            method: 'DELETE',
            headers: { ...headers, ...options.headers },
        });

        return this.handleResponse<T>(response);
    }

    /**
     * Make a PATCH request
     */
    public async patch<T>(url: string, data?: unknown, options: RequestInit = {}): Promise<T> {
        const headers = await this.getHeaders();

        const response = await fetch(url, {
            method: 'PATCH',
            headers: { ...headers, ...options.headers },
            body: data ? JSON.stringify(data) : undefined,
        });

        return this.handleResponse<T>(response);
    }

    /**
     * Make a form data POST request (for file uploads)
     */
    public async postFormData<T>(url: string, formData: FormData, options: RequestInit = {}): Promise<T> {
        const token = await this.getAccessToken();

        const headers: Record<string, string> = {
            'Authorization': `Bearer ${token}`,
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            // Don't set Content-Type for FormData, let browser set it with boundary
        };

        const response = await fetch(url, {
            method: 'POST',
            headers: { ...headers, ...options.headers },
            body: formData,
        });

        return this.handleResponse<T>(response);
    }

    /**
     * Download a file as blob (for file exports/downloads)
     */
    public async downloadBlob(url: string, data?: unknown, options: RequestInit = {}): Promise<Response> {
        const headers = await this.getHeaders();

        const response = await fetch(url, {
            method: 'POST',
            headers: { ...headers, ...options.headers },
            body: data ? JSON.stringify(data) : undefined,
            credentials: 'same-origin',
        });

        if (response.status === 401) {
            // Token is expired or invalid, clear it and throw auth error
            this.clearTokenFromStorage();
            throw new ApiError('Authentication expired. Please refresh the page and log in again.', 401);
        }

        if (!response.ok) {
            let errorMessage = `Download failed: ${response.status} ${response.statusText}`;
            let errorDetails: unknown;

            try {
                errorDetails = await response.json();
                if (errorDetails && typeof errorDetails === 'object' && 'message' in errorDetails) {
                    errorMessage = String(errorDetails.message);
                }
            } catch {
                // If JSON parsing fails, use the status text
                errorDetails = await response.text();
            }

            throw new ApiError(errorMessage, response.status, errorDetails);
        }

        return response;
    }

    /**
     * Clear authentication token (for logout)
     */
    public clearAuth(): void {
        this.clearTokenFromStorage();
    }

    /**
     * Check if user is authenticated (has a token)
     */
    public isAuthenticated(): boolean {
        return !!this.accessToken;
    }
}

// Custom error class for API errors
export class ApiError extends Error {
    public readonly status: number;
    public readonly details?: unknown;

    constructor(message: string, status: number, details?: unknown) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.details = details;

        // Ensure proper prototype chain for instanceof checks
        Object.setPrototypeOf(this, ApiError.prototype);
    }
}

// Export singleton instance
export const apiService = ApiService.getInstance();
export default apiService;
