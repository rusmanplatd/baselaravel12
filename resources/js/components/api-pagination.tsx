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

interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number;
  to: number;
  links: Array<{
    url: string | null;
    label: string;
    active: boolean;
  }>;
}

interface Props {
  meta: PaginationMeta;
  onPageChange: (page: number) => void;
  onPerPageChange: (perPage: number) => void;
  className?: string;
}

export default function ApiPagination({ 
  meta, 
  onPageChange, 
  onPerPageChange, 
  className 
}: Props) {
  const perPageOptions = [5, 10, 15, 25, 50, 100];
  const showPaginationControls = meta.last_page > 1;

  return (
    <div className={className}>
      <div className="flex items-center justify-between px-2 py-4">
        <div className="flex items-center gap-4">
          <div className="text-sm text-muted-foreground">
            Showing {meta.from} to {meta.to} of {meta.total} results
          </div>
          
          <div className="flex items-center gap-2">
            <span className="text-sm text-muted-foreground">Show:</span>
            <Select
              value={meta.per_page.toString()}
              onValueChange={(value) => onPerPageChange(parseInt(value))}
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
              {meta.current_page > 1 && (
                <PaginationItem>
                  <PaginationPrevious
                    href="#"
                    onClick={(e) => {
                      e.preventDefault();
                      onPageChange(meta.current_page - 1);
                    }}
                  />
                </PaginationItem>
              )}

              {meta.links.slice(1, -1).map((link, index) => (
                <PaginationItem key={index}>
                  {link.label === '...' ? (
                    <PaginationEllipsis />
                  ) : (
                    <PaginationLink
                      href="#"
                      onClick={(e) => {
                        e.preventDefault();
                        const pageNum = parseInt(link.label);
                        if (!isNaN(pageNum)) {
                          onPageChange(pageNum);
                        }
                      }}
                      isActive={link.active}
                    >
                      {link.label}
                    </PaginationLink>
                  )}
                </PaginationItem>
              ))}

              {meta.current_page < meta.last_page && (
                <PaginationItem>
                  <PaginationNext
                    href="#"
                    onClick={(e) => {
                      e.preventDefault();
                      onPageChange(meta.current_page + 1);
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