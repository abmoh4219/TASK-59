import { useQuery } from '@tanstack/react-query';
import { getTodayAttendance } from '../../api/attendance';
import ExceptionBadge from './ExceptionBadge';
import type { ExceptionType } from '../../types';
import { Clock, LogIn, LogOut, Timer } from 'lucide-react';

export default function AttendanceCard() {
  const { data, isLoading, error } = useQuery({
    queryKey: ['attendance', 'today'],
    queryFn: getTodayAttendance,
  });

  if (isLoading) {
    return (
      <div className="bg-surface-card border border-surface-border rounded-xl p-6 animate-pulse">
        <div className="h-6 bg-surface-hover rounded w-48 mb-4" />
        <div className="h-4 bg-surface-hover rounded w-32 mb-3" />
        <div className="h-20 bg-surface-hover rounded mb-3" />
        <div className="h-4 bg-surface-hover rounded w-24" />
      </div>
    );
  }

  if (error) {
    return (
      <div className="bg-surface-card border border-red-500/20 rounded-xl p-6">
        <p className="text-red-400">Failed to load attendance data. Please try again.</p>
      </div>
    );
  }

  if (!data) return null;

  const hours = Math.floor((data.totalMinutes || 0) / 60);
  const minutes = (data.totalMinutes || 0) % 60;
  const exceptions = (data.exceptions || []) as ExceptionType[];

  return (
    <div className="premium-card p-6 relative overflow-hidden">
      {/* Gradient accent strip */}
      <div className="absolute top-0 left-0 right-0 h-[2px] bg-gradient-to-r from-transparent via-accent/40 to-transparent" />

      {/* Header */}
      <div className="flex items-center justify-between mb-5">
        <div>
          <h3 className="text-lg font-bold text-white tracking-tight">Today's Attendance</h3>
          <p className="text-xs text-gray-500 mt-0.5">
            {new Date(data.recordDate).toLocaleDateString('en-US', {
              weekday: 'long', month: 'long', day: 'numeric', year: 'numeric',
            })}
          </p>
        </div>
        {data.shiftStart && data.shiftEnd && (
          <div className="text-right">
            <p className="text-[10px] text-gray-600 uppercase tracking-widest font-semibold">Shift</p>
            <p className="text-sm text-gray-300 font-medium mt-0.5">{data.shiftStart} – {data.shiftEnd}</p>
          </div>
        )}
      </div>

      {/* Exception Badges */}
      {exceptions.length > 0 && (
        <div className="flex flex-wrap gap-2 mb-5">
          {exceptions.map((exc) => (
            <ExceptionBadge key={exc} type={exc} />
          ))}
        </div>
      )}

      {/* Punch Timeline */}
      <div className="grid grid-cols-3 gap-3 mb-4">
        <div className="bg-gradient-to-br from-green-500/10 to-transparent rounded-xl p-4 border border-green-500/20">
          <div className="flex items-center gap-2 mb-2">
            <LogIn size={14} className="text-green-400" />
            <span className="text-[10px] text-gray-500 uppercase tracking-widest font-semibold">Clock In</span>
          </div>
          <p className="text-xl font-bold text-white tracking-tight">
            {data.firstPunchIn || '—'}
          </p>
        </div>

        <div className="bg-gradient-to-br from-red-500/10 to-transparent rounded-xl p-4 border border-red-500/20">
          <div className="flex items-center gap-2 mb-2">
            <LogOut size={14} className="text-red-400" />
            <span className="text-[10px] text-gray-500 uppercase tracking-widest font-semibold">Clock Out</span>
          </div>
          <p className="text-xl font-bold text-white tracking-tight">
            {data.lastPunchOut || '—'}
          </p>
        </div>

        <div className="bg-gradient-to-br from-accent/10 to-transparent rounded-xl p-4 border border-accent/20">
          <div className="flex items-center gap-2 mb-2">
            <Timer size={14} className="text-accent-light" />
            <span className="text-[10px] text-gray-500 uppercase tracking-widest font-semibold">Total Hours</span>
          </div>
          <p className="text-xl font-bold text-white tracking-tight">
            {hours}h {minutes}m
          </p>
        </div>
      </div>

      {/* Detailed Punches */}
      {data.punches && data.punches.length > 0 && (
        <div className="border-t border-surface-border pt-3">
          <p className="text-xs text-gray-500 mb-2">Punch Events</p>
          <div className="flex flex-wrap gap-2">
            {data.punches.map((punch: { id: number; eventTime: string; eventType: string }) => (
              <div
                key={punch.id}
                className={`flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium border ${
                  punch.eventType === 'IN'
                    ? 'bg-green-500/10 text-green-400 border-green-500/20'
                    : 'bg-red-500/10 text-red-400 border-red-500/20'
                }`}
              >
                <Clock size={12} />
                {punch.eventTime.slice(0, 5)} {punch.eventType}
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
