import React, { useState, useRef, useEffect } from 'react';
import { PlayIcon, PauseIcon, SpeakerWaveIcon } from '@heroicons/react/24/solid';
import { Message } from '@/types/chat';

interface VoiceMessagePlayerProps {
  message: Message;
  className?: string;
}

export default function VoiceMessagePlayer({ message, className = '' }: VoiceMessagePlayerProps) {
  const [isPlaying, setIsPlaying] = useState(false);
  const [currentTime, setCurrentTime] = useState(0);
  const [duration, setDuration] = useState(message.voice_duration_seconds || 0);
  const audioRef = useRef<HTMLAudioElement | null>(null);
  const intervalRef = useRef<NodeJS.Timeout | null>(null);

  useEffect(() => {
    return () => {
      if (intervalRef.current) {
        clearInterval(intervalRef.current);
      }
    };
  }, []);

  const togglePlayback = async () => {
    if (!audioRef.current) {
      // Create audio element (in real app, this would be the file URL)
      audioRef.current = new Audio();
      audioRef.current.preload = 'metadata';
      
      audioRef.current.addEventListener('loadedmetadata', () => {
        setDuration(audioRef.current?.duration || duration);
      });
      
      audioRef.current.addEventListener('ended', () => {
        setIsPlaying(false);
        setCurrentTime(0);
        if (intervalRef.current) {
          clearInterval(intervalRef.current);
        }
      });
    }

    if (isPlaying) {
      audioRef.current.pause();
      setIsPlaying(false);
      if (intervalRef.current) {
        clearInterval(intervalRef.current);
      }
    } else {
      try {
        await audioRef.current.play();
        setIsPlaying(true);
        
        // Update progress
        intervalRef.current = setInterval(() => {
          if (audioRef.current) {
            setCurrentTime(audioRef.current.currentTime);
          }
        }, 100);
      } catch (error) {
        console.error('Error playing audio:', error);
      }
    }
  };

  const formatTime = (seconds: number): string => {
    const minutes = Math.floor(seconds / 60);
    const remainingSeconds = Math.floor(seconds % 60);
    return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
  };

  const progressPercentage = duration > 0 ? (currentTime / duration) * 100 : 0;

  return (
    <div className={`flex items-center space-x-3 p-3 bg-blue-50 rounded-lg max-w-xs ${className}`}>
      {/* Play/Pause Button */}
      <button
        onClick={togglePlayback}
        className="
          flex items-center justify-center w-10 h-10 rounded-full
          bg-blue-500 hover:bg-blue-600 text-white
          transition-colors duration-200 active:scale-95
        "
      >
        {isPlaying ? (
          <PauseIcon className="h-5 w-5" />
        ) : (
          <PlayIcon className="h-5 w-5 ml-0.5" />
        )}
      </button>

      <div className="flex-1 min-w-0">
        {/* Waveform / Progress Bar */}
        <div className="relative">
          {message.voice_waveform_data ? (
            <div className="flex items-center space-x-0.5 h-8">
              {message.voice_waveform_data.split(',').slice(0, 20).map((height, index) => {
                const barHeight = Math.max(parseInt(height) / 10, 2);
                const isActive = (index / 20) * 100 < progressPercentage;
                
                return (
                  <div
                    key={index}
                    className={`rounded-full transition-colors duration-200 ${
                      isActive ? 'bg-blue-600' : 'bg-blue-300'
                    }`}
                    style={{
                      width: '3px',
                      height: `${barHeight}px`,
                    }}
                  />
                );
              })}
            </div>
          ) : (
            <div className="w-full bg-blue-200 rounded-full h-2">
              <div
                className="bg-blue-600 h-2 rounded-full transition-all duration-200"
                style={{ width: `${progressPercentage}%` }}
              />
            </div>
          )}
        </div>

        {/* Time Display */}
        <div className="flex justify-between items-center mt-1 text-xs text-blue-700">
          <span>{formatTime(currentTime)}</span>
          <div className="flex items-center space-x-1">
            <SpeakerWaveIcon className="h-3 w-3" />
            <span>{formatTime(duration)}</span>
          </div>
        </div>

        {/* Transcript */}
        {message.voice_transcript && (
          <div className="mt-2 text-sm text-gray-600 italic">
            "{message.voice_transcript}"
          </div>
        )}
      </div>
    </div>
  );
}