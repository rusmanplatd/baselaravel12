import { router } from '@inertiajs/react';
import {
  Pagination,
  PaginationContent,
  PaginationEllipsis,
  PaginationItem,
  PaginationLink,
  PaginationNext,
  PaginationPrevious,
} from '@/components/ui/pagination';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';

interface LaravelPaginationLink {
  url: string | null;
  label: string;
  active: boolean;
}

interface LaravelPaginationData {
  data: unknown[];
  current_page: number;
  first_page_url: string;
  from: number;
  last_page: number;
  last_page_url: string;
  links: LaravelPaginationLink[];
  next_page_url: string | null;
  path: string;
  per_page: number;
  prev_page_url: string | null;
  to: number;
  total: number;
}

interface Props {
  data: LaravelPaginationData;
  className?: string;
}

export default function LaravelPagination({ data, className }: Props) {
  const handlePageChange = (url: string) => {
    router.get(url, {}, {
      preserveState: true,
      preserveScroll: true,
    });
  };

  const handlePerPageChange = (perPage: string) => {
    const url = new URL(data.path, window.location.origin);
    const currentParams = new URLSearchParams(window.location.search);
    
    // Update per_page parameter
    currentParams.set('per_page', perPage);
    currentParams.delete('page'); // Reset to first page when changing per_page
    
    router.get(`${url.pathname}?${currentParams.toString()}`, {}, {
      preserveState: true,
      preserveScroll: true,
    });
  };

  const perPageOptions = [5, 10, 15, 25, 50, 100];

  // Always show the per page selector, but hide pagination controls if only one page
  const showPaginationControls = data.last_page > 1;

  return (
    <div className={className}>
      <div className="flex items-center justify-between px-2 py-4">
        <div className="flex items-center gap-4">
          <div className="text-sm text-muted-foreground">
            Showing {data.from} to {data.to} of {data.total} results
          </div>
          
          <div className="flex items-center gap-2">
            <span className="text-sm text-muted-foreground">Show:</span>
            <Select
              value={data.per_page.toString()}
              onValueChange={handlePerPageChange}
            >
              <SelectTrigger className="w-20 h-8">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {perPageOptions.map((option) => (
                  <SelectItem key={option} value={option.toString()}>
                    {option}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            <span className="text-sm text-muted-foreground">per page</span>
          </div>
        </div>
        
        {showPaginationControls && (
          <Pagination>
            <PaginationContent>
              {data.prev_page_url && (
                <PaginationItem>
                  <PaginationPrevious
                    href="#"
                    onClick={(e) => {
                      e.preventDefault();
                      handlePageChange(data.prev_page_url!);
                    }}
                  />
                </PaginationItem>
              )}

              {data.links.slice(1, -1).map((link, index) => (
                <PaginationItem key={index}>
                  {link.label === '...' ? (
                    <PaginationEllipsis />
                  ) : (
                    <PaginationLink
                      href="#"
                      onClick={(e) => {
                        e.preventDefault();
                        if (link.url) {
                          handlePageChange(link.url);
                        }
                      }}
                      isActive={link.active}
                    >
                      {link.label}
                    </PaginationLink>
                  )}
                </PaginationItem>
              ))}

              {data.next_page_url && (
                <PaginationItem>
                  <PaginationNext
                    href="#"
                    onClick={(e) => {
                      e.preventDefault();
                      handlePageChange(data.next_page_url!);
                    }}
                  />
                </PaginationItem>
              )}
            </PaginationContent>
          </Pagination>
        )}
      </div>
    </div>
  );
}