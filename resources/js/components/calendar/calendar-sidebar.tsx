import { Calendar } from '@/types/calendar';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Separator } from '@/components/ui/separator';
import { Badge } from '@/components/ui/badge';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { RefreshCw, Eye, EyeOff, MoreVertical, Share2, Settings, Users, Building, FolderOpen, Trash2 } from 'lucide-react';
import { cn } from '@/lib/utils';

interface CalendarSidebarProps {
  calendars: Calendar[];
  selectedCalendars: string[];
  onToggleCalendar: (calendarId: string) => void;
  onSelectAll: () => void;
  onDeselectAll: () => void;
  onRefresh: () => void;
  onShareCalendar?: (calendar: Calendar) => void;
  onEditCalendar?: (calendar: Calendar) => void;
  onDeleteCalendar?: (calendar: Calendar) => void;
  loading?: boolean;
}

export function CalendarSidebar({
  calendars,
  selectedCalendars,
  onToggleCalendar,
  onSelectAll,
  onDeselectAll,
  onRefresh,
  onShareCalendar,
  onEditCalendar,
  onDeleteCalendar,
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
                onShare={onShareCalendar ? () => onShareCalendar(calendar) : undefined}
                onEdit={onEditCalendar ? () => onEditCalendar(calendar) : undefined}
                onDelete={onDeleteCalendar ? () => onDeleteCalendar(calendar) : undefined}
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
  onShare?: () => void;
  onEdit?: () => void;
  onDelete?: () => void;
}

function CalendarItem({ calendar, isSelected, onToggle, onShare, onEdit, onDelete }: CalendarItemProps) {
  const getOwnerIcon = (ownerType: string) => {
    switch (ownerType.toLowerCase()) {
      case 'user': return <Users className="w-3 h-3" />;
      case 'organization': return <Building className="w-3 h-3" />;
      case 'project': return <FolderOpen className="w-3 h-3" />;
      default: return <Users className="w-3 h-3" />;
    }
  };

  const getVisibilityBadge = (visibility: string) => {
    const colors = {
      public: 'bg-green-100 text-green-800 border-green-200',
      shared: 'bg-blue-100 text-blue-800 border-blue-200',
      private: 'bg-gray-100 text-gray-800 border-gray-200',
    };
    return colors[visibility as keyof typeof colors] || colors.private;
  };
  return (
    <div className="flex items-center space-x-3 p-2 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 group">
      <Checkbox
        checked={isSelected}
        onCheckedChange={onToggle}
        className="data-[state=checked]:bg-current"
        style={{ color: calendar.color }}
      />
      
      <div
        className="w-3 h-3 rounded-full border border-gray-300 flex-shrink-0"
        style={{ backgroundColor: calendar.color }}
      />
      
      <div className="flex-1 min-w-0">
        <div className="flex items-center justify-between">
          <div className="flex items-center space-x-2 flex-1 min-w-0">
            <p className="text-sm font-medium truncate" title={calendar.name}>
              {calendar.name}
            </p>
            <Badge 
              variant="outline" 
              className={`text-xs px-1 py-0 h-4 ${getVisibilityBadge(calendar.visibility)}`}
            >
              {calendar.visibility}
            </Badge>
          </div>
          <div className="flex items-center space-x-1">
            {calendar.events_count !== undefined && (
              <Badge variant="secondary" className="text-xs h-4 px-1">
                {calendar.events_count}
              </Badge>
            )}
            {(onShare || onEdit || onDelete) && (
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button 
                    variant="ghost" 
                    size="sm" 
                    className="h-6 w-6 p-0 opacity-0 group-hover:opacity-100 transition-opacity"
                  >
                    <MoreVertical className="h-3 w-3" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-48">
                  {onShare && (
                    <DropdownMenuItem onClick={onShare}>
                      <Share2 className="mr-2 h-4 w-4" />
                      Share Calendar
                    </DropdownMenuItem>
                  )}
                  {onEdit && (
                    <DropdownMenuItem onClick={onEdit}>
                      <Settings className="mr-2 h-4 w-4" />
                      Edit Settings
                    </DropdownMenuItem>
                  )}
                  {(onShare || onEdit) && onDelete && <DropdownMenuSeparator />}
                  {onDelete && (
                    <DropdownMenuItem onClick={onDelete} className="text-red-600">
                      <Trash2 className="mr-2 h-4 w-4" />
                      Delete Calendar
                    </DropdownMenuItem>
                  )}
                </DropdownMenuContent>
              </DropdownMenu>
            )}
          </div>
        </div>
        
        <div className="flex items-center space-x-2 mt-1">
          <div className="flex items-center space-x-1">
            {getOwnerIcon(calendar.owner_type)}
            <span className="text-xs text-gray-500 capitalize">
              {calendar.owner_type.toLowerCase()}
            </span>
          </div>
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