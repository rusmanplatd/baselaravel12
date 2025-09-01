import { useState, useEffect, useCallback } from 'react';
import { apiService } from '@/services/ApiService';

interface PaginatedResponse<T> {
  data: T[];
  current_page: number;
  first_page_url: string;
  from: number;
  last_page: number;
  last_page_url: string;
  links: Array<{
    url: string | null;
    label: string;
    active: boolean;
  }>;
  next_page_url: string | null;
  path: string;
  per_page: number;
  prev_page_url: string | null;
  to: number;
  total: number;
}

interface UseApiDataOptions {
  endpoint: string;
  initialFilters?: Record<string, string>;
  initialSort?: string;
  initialPerPage?: number;
}

interface UseApiDataReturn<T> {
  data: PaginatedResponse<T> | null;
  loading: boolean;
  error: string | null;
  filters: Record<string, string>;
  sort: string;
  updateFilter: (key: string, value: string) => void;
  updateSort: (field: string) => void;
  updatePerPage: (perPage: number) => void;
  goToPage: (page: number) => void;
  refresh: () => void;
}

// Helper function to get URL parameters
function getUrlParams(): Record<string, string> {
  const urlParams = new URLSearchParams(window.location.search);
  const params: Record<string, string> = {};
  
  urlParams.forEach((value, key) => {
    params[key] = value;
  });
  
  return params;
}

// Helper function to update URL without reloading the page
function updateUrl(params: Record<string, string | number | undefined>) {
  const urlParams = new URLSearchParams();
  
  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== '' && value !== null) {
      urlParams.set(key, value.toString());
    }
  });
  
  const newUrl = `${window.location.pathname}${urlParams.toString() ? '?' + urlParams.toString() : ''}`;
  window.history.replaceState({}, '', newUrl);
}

export function useApiData<T>({
  endpoint,
  initialFilters = {},
  initialSort = '',
  initialPerPage = 15,
}: UseApiDataOptions): UseApiDataReturn<T> {
  // Initialize state from URL parameters
  const urlParams = getUrlParams();
  
  const [data, setData] = useState<PaginatedResponse<T> | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  
  // Initialize from URL or fallback to initial values
  const [filters, setFilters] = useState(() => {
    const urlFilters: Record<string, string> = {};
    Object.keys(initialFilters).forEach(key => {
      urlFilters[key] = urlParams[key] || initialFilters[key] || '';
    });
    return urlFilters;
  });
  
  const [sort, setSort] = useState(urlParams.sort || initialSort);
  const [perPage, setPerPage] = useState(() => {
    const urlPerPage = parseInt(urlParams.per_page || '');
    return [5, 10, 15, 25, 50, 100].includes(urlPerPage) ? urlPerPage : initialPerPage;
  });
  const [page, setPage] = useState(() => {
    const urlPage = parseInt(urlParams.page || '');
    return urlPage > 0 ? urlPage : 1;
  });

  const fetchData = useCallback(async () => {
    setLoading(true);
    setError(null);

    try {
      const params = new URLSearchParams();
      
      // Add filters
      Object.entries(filters).forEach(([key, value]) => {
        if (value && value.trim() !== '') {
          params.append(`filter[${key}]`, value);
        }
      });

      // Add sort
      if (sort) {
        params.append('sort', sort);
      }

      // Add pagination
      if (page > 1) {
        params.append('page', page.toString());
      }
      if (perPage !== 15) {
        params.append('per_page', perPage.toString());
      }

      const url = `/api/v1/geo/${endpoint}${params.toString() ? '?' + params.toString() : ''}`;
      const data = await apiService.get<PaginatedResponse<T>>(url);
      
      setData(data);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An error occurred');
    } finally {
      setLoading(false);
    }
  }, [endpoint, filters, sort, perPage, page]);

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  // Function to sync all current state to URL
  const syncToUrl = useCallback((newFilters?: Record<string, string>, newSort?: string, newPerPage?: number, newPage?: number) => {
    const currentFilters = newFilters || filters;
    const currentSort = newSort !== undefined ? newSort : sort;
    const currentPerPage = newPerPage || perPage;
    const currentPage = newPage || page;
    
    const urlParams: Record<string, string | number | undefined> = {
      ...currentFilters,
      sort: currentSort || undefined,
      per_page: currentPerPage !== 15 ? currentPerPage : undefined,
      page: currentPage !== 1 ? currentPage : undefined,
    };
    
    updateUrl(urlParams);
  }, [filters, sort, perPage, page]);

  const updateFilter = useCallback((key: string, value: string) => {
    const newFilters = { ...filters, [key]: value };
    setFilters(newFilters);
    setPage(1); // Reset to first page when filtering
    syncToUrl(newFilters, sort, perPage, 1);
  }, [filters, sort, perPage, syncToUrl]);

  const updateSort = useCallback((field: string) => {
    const newSort = sort === field ? `-${field}` : sort === `-${field}` ? '' : field;
    setSort(newSort);
    setPage(1); // Reset to first page when sorting
    syncToUrl(filters, newSort, perPage, 1);
  }, [sort, filters, perPage, syncToUrl]);

  const updatePerPage = useCallback((newPerPage: number) => {
    setPerPage(newPerPage);
    setPage(1); // Reset to first page when changing per page
    syncToUrl(filters, sort, newPerPage, 1);
  }, [filters, sort, syncToUrl]);

  const goToPage = useCallback((newPage: number) => {
    setPage(newPage);
    syncToUrl(filters, sort, perPage, newPage);
  }, [filters, sort, perPage, syncToUrl]);

  const refresh = useCallback(() => {
    fetchData();
  }, [fetchData]);

  return {
    data,
    loading,
    error,
    filters,
    sort,
    updateFilter,
    updateSort,
    updatePerPage,
    goToPage,
    refresh,
  };
}