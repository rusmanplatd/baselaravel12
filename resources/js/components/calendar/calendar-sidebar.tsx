import { Calendar } from '@/types/calendar';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Separator } from '@/components/ui/separator';
import { RefreshCw, CheckSquare, Square, Eye, EyeOff } from 'lucide-react';
import { cn } from '@/lib/utils';

interface CalendarSidebarProps {
  calendars: Calendar[];
  selectedCalendars: string[];
  onToggleCalendar: (calendarId: string) => void;
  onSelectAll: () => void;
  onDeselectAll: () => void;
  onRefresh: () => void;
  loading?: boolean;
}

export function CalendarSidebar({
  calendars,
  selectedCalendars,
  onToggleCalendar,
  onSelectAll,
  onDeselectAll,
  onRefresh,
  loading = false,
}: CalendarSidebarProps) {
  const allSelected = calendars.length > 0 && selectedCalendars.length === calendars.length;
  const someSelected = selectedCalendars.length > 0 && selectedCalendars.length < calendars.length;

  return (
    <div className="flex flex-col h-full p-4">
      {/* Header */}
      <div className="flex items-center justify-between mb-4">
        <h3 className="text-lg font-semibold">My Calendars</h3>
        <Button
          variant="ghost"
          size="sm"
          onClick={onRefresh}
          disabled={loading}
        >
          <RefreshCw className={cn("h-4 w-4", loading && "animate-spin")} />
        </Button>
      </div>

      {/* Select All Controls */}
      {calendars.length > 0 && (
        <>
          <div className="flex items-center justify-between mb-2">
            <Button
              variant="ghost"
              size="sm"
              onClick={allSelected ? onDeselectAll : onSelectAll}
              className="h-8 p-0 text-sm font-normal"
            >
              {allSelected ? (
                <>
                  <EyeOff className="h-4 w-4 mr-2" />
                  Hide All
                </>
              ) : (
                <>
                  <Eye className="h-4 w-4 mr-2" />
                  Show All
                </>
              )}
            </Button>
            <span className="text-sm text-gray-500">
              {selectedCalendars.length} of {calendars.length}
            </span>
          </div>
          <Separator className="mb-4" />
        </>
      )}

      {/* Calendar List */}
      <ScrollArea className="flex-1">
        <div className="space-y-2">
          {calendars.length === 0 ? (
            <div className="text-center py-8 text-gray-500">
              <p className="text-sm">No calendars found</p>
              <p className="text-xs mt-1">Create your first calendar to get started</p>
            </div>
          ) : (
            calendars.map((calendar) => (
              <CalendarItem
                key={calendar.id}
                calendar={calendar}
                isSelected={selectedCalendars.includes(calendar.id)}
                onToggle={() => onToggleCalendar(calendar.id)}
              />
            ))
          )}
        </div>
      </ScrollArea>
    </div>
  );
}

interface CalendarItemProps {
  calendar: Calendar;
  isSelected: boolean;
  onToggle: () => void;
}

function CalendarItem({ calendar, isSelected, onToggle }: CalendarItemProps) {
  return (
    <div className="flex items-center space-x-3 p-2 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer group">
      <Checkbox
        checked={isSelected}
        onCheckedChange={onToggle}
        className="data-[state=checked]:bg-current"
        style={{ color: calendar.color }}
      />
      
      <div
        className="w-3 h-3 rounded-full border border-gray-300"
        style={{ backgroundColor: calendar.color }}
      />
      
      <div className="flex-1 min-w-0">
        <div className="flex items-center justify-between">
          <p className="text-sm font-medium truncate" title={calendar.name}>
            {calendar.name}
          </p>
          {calendar.events_count !== undefined && (
            <span className="text-xs text-gray-500 ml-2">
              {calendar.events_count}
            </span>
          )}
        </div>
        
        <div className="flex items-center space-x-2 mt-1">
          <span className="text-xs text-gray-500 capitalize">
            {calendar.owner_type.toLowerCase()}
          </span>
          <span className="text-xs text-gray-400">•</span>
          <span className="text-xs text-gray-500 truncate" title={calendar.owner_name}>
            {calendar.owner_name}
          </span>
        </div>
        
        {calendar.description && (
          <p className="text-xs text-gray-400 mt-1 truncate" title={calendar.description}>
            {calendar.description}
          </p>
        )}
      </div>
    </div>
  );
}