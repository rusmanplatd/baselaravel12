import * as React from "react";
import { Search, X } from "lucide-react";
import { cn } from "@/lib/utils";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Input } from "@/components/ui/input";

export interface SearchableSelectItem {
  value: string;
  label: string;
  searchText?: string; // Optional additional text to search through
}

interface SearchableSelectProps {
  placeholder?: string;
  items: SearchableSelectItem[];
  value?: string;
  onValueChange?: (value: string) => void;
  disabled?: boolean;
  className?: string;
  emptyLabel?: string;
  searchPlaceholder?: string;
  showClearButton?: boolean;
  onRefetch?: (searchQuery: string) => void;
  refetchDelay?: number;
  onClear?: () => void;
}

export function SearchableSelect({
  placeholder = "Select an option...",
  items,
  value,
  onValueChange,
  disabled = false,
  className,
  emptyLabel = "All",
  searchPlaceholder = "Search...",
  showClearButton = true,
  onRefetch,
  refetchDelay = 300,
  onClear,
}: SearchableSelectProps) {
  const [open, setOpen] = React.useState(false);
  const [searchQuery, setSearchQuery] = React.useState("");

  // Debounced refetch effect
  React.useEffect(() => {
    if (!onRefetch || !searchQuery.trim()) return;

    const timeoutId = setTimeout(() => {
      onRefetch(searchQuery);
    }, refetchDelay);

    return () => clearTimeout(timeoutId);
  }, [searchQuery, onRefetch, refetchDelay]);

  const filteredItems = React.useMemo(() => {
    if (!searchQuery.trim()) return items;
    
    const query = searchQuery.toLowerCase();
    return items.filter(item => 
      item.label.toLowerCase().includes(query) ||
      item.value.toLowerCase().includes(query) ||
      item.searchText?.toLowerCase().includes(query)
    );
  }, [items, searchQuery]);

  const selectedItem = value === "" ? null : items.find(item => item.value === value);

  const handleOpenChange = (isOpen: boolean) => {
    setOpen(isOpen);
    if (!isOpen) {
      setSearchQuery("");
    }
  };

  const handleValueChange = (newValue: string) => {
    // Convert the special empty value back to empty string
    const actualValue = newValue === "all" ? "" : newValue;
    onValueChange?.(actualValue);
    
    // If clearing (selecting empty option), trigger onClear callback
    if (actualValue === "" && onClear) {
      onClear();
    }
  };

  const handleClear = () => {
    onValueChange?.("");
    onClear?.();
    setOpen(false);
  };

  return (
    <div className="relative">
      <Select 
        open={open} 
        onOpenChange={handleOpenChange}
        value={value === "" ? "all" : value} 
        onValueChange={handleValueChange}
        disabled={disabled}
      >
        <SelectTrigger className={cn("w-full", className, showClearButton && value && value !== "" && "pr-8")}>
          <SelectValue placeholder={placeholder}>
            {selectedItem?.label || (value === "" ? emptyLabel : value) || undefined}
          </SelectValue>
        </SelectTrigger>
      <SelectContent>
        <div className="flex items-center px-3 pb-2">
          <Search className="mr-2 h-4 w-4 shrink-0 opacity-50" />
          <Input
            placeholder={searchPlaceholder}
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            className="h-8 border-0 p-0 text-sm ring-0 focus-visible:ring-0 focus-visible:ring-offset-0"
            autoFocus
          />
        </div>
        <div className="border-t border-border mx-1"></div>
        {emptyLabel && (
          <SelectItem value="all">
            {emptyLabel}
          </SelectItem>
        )}
        {filteredItems.length > 0 ? (
          filteredItems.map((item) => (
            <SelectItem key={item.value} value={item.value}>
              {item.label}
            </SelectItem>
          ))
        ) : (
          <div className="py-2 px-3 text-sm text-muted-foreground">
            No options found
          </div>
        )}
        </SelectContent>
      </Select>
      {showClearButton && value && value !== "" && (
        <button
          type="button"
          onClick={handleClear}
          className="absolute right-2 top-1/2 -translate-y-1/2 h-4 w-4 shrink-0 opacity-50 hover:opacity-100 transition-opacity z-10"
        >
          <X className="h-4 w-4" />
        </button>
      )}
    </div>
  );
}