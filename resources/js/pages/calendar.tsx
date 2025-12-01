import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { CalendarView } from '@/components/calendar/calendar-view';
import { CalendarSidebar } from '@/components/calendar/calendar-sidebar';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Plus, Calendar as CalendarIcon } from 'lucide-react';
import { CreateCalendarDialog } from '@/components/calendar/create-calendar-dialog';
import { CreateEventDialog } from '@/components/calendar/create-event-dialog';
import { useCalendar } from '@/hooks/useCalendar';
import { User } from '@/types';

interface CalendarPageProps {
  auth: {
    user: User;
  };
}

export default function CalendarPage({ auth }: CalendarPageProps) {
  const [showCreateCalendar, setShowCreateCalendar] = useState(false);
  const [showCreateEvent, setShowCreateEvent] = useState(false);
  
  const calendar = useCalendar();

  return (
    <AppLayout
      user={auth.user}
      header={
        <div className="flex items-center justify-between">
          <div className="flex items-center space-x-2">
            <CalendarIcon className="h-6 w-6" />
            <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
              Calendar
            </h2>
          </div>
          <div className="flex items-center space-x-2">
            <Button
              variant="outline"
              size="sm"
              onClick={() => setShowCreateCalendar(true)}
            >
              <Plus className="h-4 w-4 mr-2" />
              New Calendar
            </Button>
            <Button
              size="sm"
              onClick={() => setShowCreateEvent(true)}
              disabled={calendar.selectedCalendars.length === 0}
            >
              <Plus className="h-4 w-4 mr-2" />
              New Event
            </Button>
          </div>
        </div>
      }
    >
      <Head title="Calendar" />

      <div className="py-12">
        <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
          <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
            <div className="flex h-[800px]">
              {/* Sidebar */}
              <div className="w-80 border-r border-gray-200 dark:border-gray-700">
                <CalendarSidebar
                  calendars={calendar.visibleCalendars}
                  selectedCalendars={calendar.selectedCalendars}
                  onToggleCalendar={calendar.toggleCalendar}
                  onSelectAll={calendar.selectAllCalendars}
                  onDeselectAll={calendar.deselectAllCalendars}
                  onRefresh={calendar.refreshData}
                  loading={calendar.loading}
                />
              </div>

              {/* Main Calendar View */}
              <div className="flex-1">
                <CalendarView
                  currentDate={calendar.currentDate}
                  viewType={calendar.viewType}
                  events={calendar.events}
                  calendars={calendar.visibleCalendars}
                  onDateChange={calendar.setCurrentDate}
                  onViewTypeChange={calendar.setViewType}
                  onEventClick={(event) => {
                    // Handle event click - could open edit dialog
                    console.log('Event clicked:', event);
                  }}
                  loading={calendar.loading}
                />
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Dialogs */}
      <CreateCalendarDialog
        open={showCreateCalendar}
        onOpenChange={setShowCreateCalendar}
        onCalendarCreated={(calendar) => {
          // Calendar is automatically added by the hook
          setShowCreateCalendar(false);
        }}
        currentUser={auth.user}
      />

      <CreateEventDialog
        open={showCreateEvent}
        onOpenChange={setShowCreateEvent}
        availableCalendars={calendar.visibleCalendars.filter(c => 
          calendar.selectedCalendars.includes(c.id)
        )}
        onEventCreated={(event) => {
          // Event is automatically added by the hook
          setShowCreateEvent(false);
        }}
      />
    </AppLayout>
  );
}