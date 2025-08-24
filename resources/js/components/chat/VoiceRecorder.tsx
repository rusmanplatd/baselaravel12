import React, { useState, useRef, useEffect, useCallback } from 'react';
import { MicrophoneIcon, StopIcon, PlayIcon, PauseIcon, XMarkIcon } from '@heroicons/react/24/solid';
import { VoiceRecording } from '@/types/chat';

interface VoiceRecorderProps {
  onRecordingComplete: (recording: VoiceRecording) => void;
  onCancel: () => void;
  isOpen: boolean;
}

export default function VoiceRecorder({ onRecordingComplete, onCancel, isOpen }: VoiceRecorderProps) {
  const [isRecording, setIsRecording] = useState(false);
  const [isPlaying, setIsPlaying] = useState(false);
  const [duration, setDuration] = useState(0);
  const [audioBlob, setAudioBlob] = useState<Blob | null>(null);
  const [audioUrl, setAudioUrl] = useState<string | null>(null);
  const [waveformData, setWaveformData] = useState<number[]>([]);

  const mediaRecorder = useRef<MediaRecorder | null>(null);
  const audioChunks = useRef<Blob[]>([]);
  const audioElement = useRef<HTMLAudioElement | null>(null);
  const timerInterval = useRef<NodeJS.Timeout | null>(null);
  const animationFrame = useRef<number | null>(null);

  const cleanup = useCallback(() => {
    if (timerInterval.current) {
      clearInterval(timerInterval.current);
      timerInterval.current = null;
    }
    
    if (animationFrame.current) {
      cancelAnimationFrame(animationFrame.current);
      animationFrame.current = null;
    }
    
    if (audioUrl) {
      URL.revokeObjectURL(audioUrl);
    }
    
    setIsRecording(false);
    setIsPlaying(false);
    setDuration(0);
    setAudioBlob(null);
    setAudioUrl(null);
    setWaveformData([]);
    audioChunks.current = [];
  }, [audioUrl]);

  useEffect(() => {
    if (!isOpen) {
      cleanup();
    }
    
    return cleanup;
  }, [isOpen, cleanup]);

  const startRecording = async () => {
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      
      mediaRecorder.current = new MediaRecorder(stream);
      audioChunks.current = [];
      
      mediaRecorder.current.ondataavailable = (event) => {
        audioChunks.current.push(event.data);
      };
      
      mediaRecorder.current.onstop = () => {
        const blob = new Blob(audioChunks.current, { type: 'audio/wav' });
        const url = URL.createObjectURL(blob);
        setAudioBlob(blob);
        setAudioUrl(url);
        
        stream.getTracks().forEach(track => track.stop());
      };
      
      mediaRecorder.current.start();
      setIsRecording(true);
      setDuration(0);
      
      // Start timer
      timerInterval.current = setInterval(() => {
        setDuration(prev => prev + 1);
      }, 1000);
      
      // Generate mock waveform data during recording
      generateWaveformData();
      
    } catch (error) {
      console.error('Error starting recording:', error);
      alert('Could not access microphone. Please check permissions.');
    }
  };

  const stopRecording = () => {
    if (mediaRecorder.current && isRecording) {
      mediaRecorder.current.stop();
      setIsRecording(false);
      
      if (timerInterval.current) {
        clearInterval(timerInterval.current);
        timerInterval.current = null;
      }
    }
  };

  const generateWaveformData = () => {
    const data: number[] = [];
    for (let i = 0; i < 50; i++) {
      data.push(Math.random() * 100);
    }
    setWaveformData(data);
  };

  const playRecording = () => {
    if (!audioUrl) return;
    
    if (!audioElement.current) {
      audioElement.current = new Audio(audioUrl);
      audioElement.current.onended = () => setIsPlaying(false);
    }
    
    if (isPlaying) {
      audioElement.current.pause();
      setIsPlaying(false);
    } else {
      audioElement.current.play();
      setIsPlaying(true);
    }
  };

  const sendRecording = () => {
    if (audioBlob) {
      onRecordingComplete({
        blob: audioBlob,
        duration,
        waveformData
      });
    }
  };

  const formatTime = (seconds: number): string => {
    const minutes = Math.floor(seconds / 60);
    const remainingSeconds = seconds % 60;
    return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
  };

  if (!isOpen) return null;

  return (
    <div className="bg-white border border-gray-200 rounded-lg p-4 shadow-lg">
      <div className="flex items-center justify-between mb-4">
        <h3 className="text-lg font-medium text-gray-900">Voice Message</h3>
        <button
          onClick={onCancel}
          className="text-gray-400 hover:text-gray-600"
        >
          <XMarkIcon className="h-5 w-5" />
        </button>
      </div>
      
      {/* Waveform visualization */}
      <div className="flex items-center justify-center h-16 mb-4 bg-gray-50 rounded">
        {waveformData.length > 0 ? (
          <div className="flex items-center space-x-1">
            {waveformData.slice(0, 30).map((height, index) => (
              <div
                key={index}
                className={`bg-blue-500 rounded-full transition-all duration-200 ${
                  isRecording ? 'animate-pulse' : ''
                }`}
                style={{
                  width: '3px',
                  height: `${Math.max(height / 5, 4)}px`,
                  opacity: isRecording ? 0.8 : 0.5
                }}
              />
            ))}
          </div>
        ) : (
          <div className="text-gray-500 text-sm">
            Click record to start
          </div>
        )}
      </div>
      
      {/* Timer */}
      <div className="text-center text-lg font-mono text-gray-700 mb-4">
        {formatTime(duration)}
      </div>
      
      {/* Controls */}
      <div className="flex items-center justify-center space-x-4">
        {!isRecording && !audioBlob && (
          <button
            onClick={startRecording}
            className="
              flex items-center justify-center w-12 h-12 rounded-full
              bg-red-500 hover:bg-red-600 text-white
              transition-colors duration-200 active:scale-95
            "
          >
            <MicrophoneIcon className="h-6 w-6" />
          </button>
        )}
        
        {isRecording && (
          <button
            onClick={stopRecording}
            className="
              flex items-center justify-center w-12 h-12 rounded-full
              bg-red-600 hover:bg-red-700 text-white animate-pulse
              transition-colors duration-200 active:scale-95
            "
          >
            <StopIcon className="h-6 w-6" />
          </button>
        )}
        
        {audioBlob && (
          <>
            <button
              onClick={playRecording}
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
            
            <button
              onClick={sendRecording}
              className="
                px-4 py-2 bg-green-500 hover:bg-green-600 text-white
                rounded-lg font-medium transition-colors duration-200
                active:scale-95
              "
            >
              Send
            </button>
            
            <button
              onClick={() => {
                setAudioBlob(null);
                setAudioUrl(null);
                setWaveformData([]);
                setDuration(0);
              }}
              className="
                px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white
                rounded-lg font-medium transition-colors duration-200
                active:scale-95
              "
            >
              Re-record
            </button>
          </>
        )}
      </div>
      
      {isRecording && (
        <div className="text-center text-sm text-red-600 mt-2 animate-pulse">
          Recording... Tap stop when finished
        </div>
      )}
    </div>
  );
}