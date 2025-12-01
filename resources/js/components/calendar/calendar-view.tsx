import { Calendar, CalendarEvent } from '@/types/calendar';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { ChevronLeft, ChevronRight, Calendar as CalendarIcon } from 'lucide-react';
import { format, addMonths, subMonths, startOfMonth, endOfMonth, eachDayOfInterval, isSameMonth, isSameDay, isToday } from 'date-fns';
import { cn } from '@/lib/utils';
import { parseISO } from 'date-fns';

interface CalendarViewProps {
  currentDate: Date;
  viewType: 'month' | 'week' | 'day' | 'agenda';
  events: CalendarEvent[];
  calendars: Calendar[];
  onDateChange: (date: Date) => void;
  onViewTypeChange: (viewType: 'month' | 'week' | 'day' | 'agenda') => void;
  onEventClick: (event: CalendarEvent) => void;
  loading?: boolean;
}

export function CalendarView({
  currentDate,
  viewType,
  events,
  calendars,
  onDateChange,
  onViewTypeChange,
  onEventClick,
  loading = false,
}: CalendarViewProps) {
  const navigateMonth = (direction: 'prev' | 'next') => {
    const newDate = direction === 'prev' 
      ? subMonths(currentDate, 1)
      : addMonths(currentDate, 1);
    onDateChange(newDate);
  };

  const goToToday = () => {
    onDateChange(new Date());
  };

  return (
    <div className="flex flex-col h-full">
      {/* Header */}
      <div className="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
        <div className="flex items-center space-x-4">
          <Button variant="outline" size="sm" onClick={goToToday}>
            Today
          </Button>
          
          <div className="flex items-center space-x-1">
            <Button
              variant="ghost"
              size="sm"
              onClick={() => navigateMonth('prev')}
            >
              <ChevronLeft className="h-4 w-4" />
            </Button>
            <Button
              variant="ghost"
              size="sm"
              onClick={() => navigateMonth('next')}
            >
              <ChevronRight className="h-4 w-4" />
            </Button>
          </div>

          <h2 className="text-xl font-semibold">
            {format(currentDate, 'MMMM yyyy')}
          </h2>
        </div>

        <div className="flex items-center space-x-2">
          <Select value={viewType} onValueChange={(value: any) => onViewTypeChange(value)}>
            <SelectTrigger className="w-32">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="month">Month</SelectItem>
              <SelectItem value="week">Week</SelectItem>
              <SelectItem value="day">Day</SelectItem>
              <SelectItem value="agenda">Agenda</SelectItem>
            </SelectContent>
          </Select>
        </div>
      </div>

      {/* Calendar Content */}
      <div className="flex-1 p-4">
        {loading ? (
          <div className="flex items-center justify-center h-full">
            <div className="text-center">
              <CalendarIcon className="h-8 w-8 mx-auto mb-4 text-gray-400 animate-pulse" />
              <p className="text-gray-500">Loading calendar...</p>
            </div>
          </div>
        ) : viewType === 'month' ? (
          <MonthView
            currentDate={currentDate}
            events={events}
            calendars={calendars}
            onEventClick={onEventClick}
          />
        ) : viewType === 'agenda' ? (
          <AgendaView
            events={events}
            calendars={calendars}
            onEventClick={onEventClick}
          />
        ) : (
          <div className="flex items-center justify-center h-full text-gray-500">
            <p>{viewType} view coming soon...</p>
          </div>
        )}
      </div>
    </div>
  );
}

interface MonthViewProps {
  currentDate: Date;
  events: CalendarEvent[];
  calendars: Calendar[];
  onEventClick: (event: CalendarEvent) => void;
}

function MonthView({ currentDate, events, calendars, onEventClick }: MonthViewProps) {
  const monthStart = startOfMonth(currentDate);
  const monthEnd = endOfMonth(currentDate);
  
  // Get all days to display (including days from previous/next month to fill the grid)
  const calendarStart = new Date(monthStart);
  calendarStart.setDate(calendarStart.getDate() - monthStart.getDay());
  
  const calendarEnd = new Date(monthEnd);
  calendarEnd.setDate(calendarEnd.getDate() + (6 - monthEnd.getDay()));
  
  const days = eachDayOfInterval({ start: calendarStart, end: calendarEnd });

  // Group events by date
  const eventsByDate = events.reduce((acc, event) => {
    const dateKey = format(parseISO(event.starts_at), 'yyyy-MM-dd');
    if (!acc[dateKey]) {
      acc[dateKey] = [];
    }
    acc[dateKey].push(event);
    return acc;
  }, {} as Record<string, CalendarEvent[]>);

  const calendarMap = calendars.reduce((acc, calendar) => {
    acc[calendar.id] = calendar;
    return acc;
  }, {} as Record<string, Calendar>);

  return (
    <div className="h-full">
      {/* Days of week header */}
      <div className="grid grid-cols-7 gap-px mb-2">
        {['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map((day) => (
          <div key={day} className="p-2 text-center text-sm font-medium text-gray-700 dark:text-gray-300">
            {day}
          </div>
        ))}
      </div>

      {/* Calendar grid */}
      <div className="grid grid-cols-7 gap-px h-[calc(100%-2rem)] bg-gray-200 dark:bg-gray-600">
        {days.map((day) => {
          const dateKey = format(day, 'yyyy-MM-dd');
          const dayEvents = eventsByDate[dateKey] || [];
          const isCurrentMonth = isSameMonth(day, currentDate);
          const isToday_ = isToday(day);

          return (
            <div
              key={day.toISOString()}
              className={cn(
                'bg-white dark:bg-gray-800 p-2 min-h-[120px] overflow-hidden',
                !isCurrentMonth && 'text-gray-400 bg-gray-50 dark:bg-gray-900'
              )}
            >
              <div
                className={cn(
                  'text-sm font-medium mb-1',
                  isToday_ && 'bg-blue-500 text-white rounded-full w-6 h-6 flex items-center justify-center'
                )}
              >
                {format(day, 'd')}
              </div>

              {/* Events */}
              <div className="space-y-1">
                {dayEvents.slice(0, 3).map((event) => {
                  const calendar = calendarMap[event.calendar_id];
                  return (
                    <div
                      key={event.id}
                      className="text-xs p-1 rounded cursor-pointer truncate hover:opacity-80"
                      style={{ backgroundColor: event.color + '20', color: event.color }}
                      onClick={() => onEventClick(event)}
                      title={event.title}
                    >
                      {event.is_all_day ? event.title : `${format(parseISO(event.starts_at), 'HH:mm')} ${event.title}`}
                    </div>
                  );
                })}
                {dayEvents.length > 3 && (
                  <div className="text-xs text-gray-500 text-center">
                    +{dayEvents.length - 3} more
                  </div>
                )}
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}

interface AgendaViewProps {
  events: CalendarEvent[];
  calendars: Calendar[];
  onEventClick: (event: CalendarEvent) => void;
}

function AgendaView({ events, calendars, onEventClick }: AgendaViewProps) {
  const sortedEvents = [...events].sort((a, b) => 
    new Date(a.starts_at).getTime() - new Date(b.starts_at).getTime()
  );

  const calendarMap = calendars.reduce((acc, calendar) => {
    acc[calendar.id] = calendar;
    return acc;
  }, {} as Record<string, Calendar>);

  if (sortedEvents.length === 0) {
    return (
      <div className="flex items-center justify-center h-full text-gray-500">
        <div className="text-center">
          <CalendarIcon className="h-12 w-12 mx-auto mb-4 text-gray-300" />
          <p>No events to display</p>
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-4">
      {sortedEvents.map((event) => {
        const calendar = calendarMap[event.calendar_id];
        const startDate = parseISO(event.starts_at);
        
        return (
          <div
            key={event.id}
            className="border rounded-lg p-4 cursor-pointer hover:shadow-md transition-shadow"
            onClick={() => onEventClick(event)}
          >
            <div className="flex items-start space-x-3">
              <div
                className="w-1 h-full rounded"
                style={{ backgroundColor: event.color }}
              />
              
              <div className="flex-1">
                <div className="flex items-center justify-between">
                  <h3 className="font-medium">{event.title}</h3>
                  <span className="text-sm text-gray-500">
                    {calendar?.name}
                  </span>
                </div>
                
                <div className="text-sm text-gray-600 mt-1">
                  {event.is_all_day ? (
                    <span>All day • {format(startDate, 'MMMM d, yyyy')}</span>
                  ) : (
                    <span>
                      {format(startDate, 'MMMM d, yyyy • h:mm a')}
                      {event.ends_at && ` - ${format(parseISO(event.ends_at), 'h:mm a')}`}
                    </span>
                  )}
                </div>
                
                {event.location && (
                  <div className="text-sm text-gray-500 mt-1">
                    📍 {event.location}
                  </div>
                )}
                
                {event.description && (
                  <div className="text-sm text-gray-700 mt-2 line-clamp-2">
                    {event.description}
                  </div>
                )}
              </div>
            </div>
          </div>
        );
      })}
    </div>
  );
}