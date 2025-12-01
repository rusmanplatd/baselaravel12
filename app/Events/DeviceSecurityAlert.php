<?php

namespace App\Events;

use App\Models\UserDevice;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeviceSecurityAlert implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public UserDevice $device,
        public array $alertData
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->device->user_id}.security"),
            new PrivateChannel("device.{$this->device->id}.security"),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'device_id' => $this->device->id,
            'device_name' => $this->device->display_name,
            'alert_type' => $this->alertData['type'],
            'severity' => 'high',
            'message' => $this->getAlertMessage(),
            'timestamp' => now()->toISOString(),
            'data' => $this->alertData,
        ];
    }

    /**
     * Get the broadcast event name.
     */
    public function broadcastAs(): string
    {
        return 'device.security.alert';
    }

    private function getAlertMessage(): string
    {
        return match ($this->alertData['type']) {
            'suspicious_activity' => "Suspicious activity detected on device {$this->device->display_name}",
            'unauthorized_access' => "Unauthorized access attempt detected on device {$this->device->display_name}",
            'key_compromise' => "Potential key compromise detected on device {$this->device->display_name}",
            'device_lock' => "Device {$this->device->display_name} has been locked due to security concerns",
            default => "Security alert for device {$this->device->display_name}",
        };
    }
}
