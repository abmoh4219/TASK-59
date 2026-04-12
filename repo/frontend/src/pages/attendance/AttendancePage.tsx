import { useQuery } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import { getTodayAttendance, getAttendanceHistory } from '../../api/attendance';
import AttendanceCard from '../../components/attendance/AttendanceCard';
import PolicyHint from '../../components/attendance/PolicyHint';
import ExceptionBadge from '../../components/attendance/ExceptionBadge';
import type { ExceptionType } from '../../types';
import { Plus, Calendar, Clock } from 'lucide-react';

export default function AttendancePage() {
  const navigate = useNavigate();

  const { data: todayData } = useQuery({
    queryKey: ['attendance', 'today'],
    queryFn: getTodayAttendance,
  });

  const { data: historyData, isLoading: historyLoading } = useQuery({
    queryKey: ['attendance', 'history'],
    queryFn: () => getAttendanceHistory({ page: 1 }),
  });

  const exceptions = (todayData?.exceptions || []) as ExceptionType[];
  const hasExceptions = exceptions.length > 0 && !exceptions.includes('APPROVED_OFFSITE');

  // Check if within 7-day filing window (approximate — real check is server-side)
  const canFileRequest = hasExceptions;

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-white">Attendance</h1>
        {canFileRequest && (
          <button
            onClick={() => navigate('/attendance/request')}
            className="flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-accent to-accent-dark rounded-lg text-white text-sm font-medium hover:shadow-glow transition-all"
          >
            <Plus size={16} />
            Submit Request
          </button>
        )}
      </div>

      {/* Today's Attendance Card */}
      <AttendanceCard />

      {/* Policy Hints */}
      {exceptions.length > 0 && <PolicyHint exceptions={exceptions} />}

      {/* History */}
      <div className="bg-surface-card border border-surface-border rounded-xl p-6">
        <h3 className="text-lg font-semibold text-white mb-4 flex items-center gap-2">
          <Calendar size={18} />
          Attendance History
        </h3>

        {historyLoading ? (
          <div className="space-y-3">
            {[1, 2, 3].map((i) => (
              <div key={i} className="h-12 bg-surface-hover rounded-lg animate-pulse" />
            ))}
          </div>
        ) : historyData && historyData.data.length > 0 ? (
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead>
                <tr className="border-b border-surface-border">
                  <th className="text-left py-3 px-4 text-xs font-medium text-gray-500 uppercase">Date</th>
                  <th className="text-left py-3 px-4 text-xs font-medium text-gray-500 uppercase">Clock In</th>
                  <th className="text-left py-3 px-4 text-xs font-medium text-gray-500 uppercase">Clock Out</th>
                  <th className="text-left py-3 px-4 text-xs font-medium text-gray-500 uppercase">Hours</th>
                  <th className="text-left py-3 px-4 text-xs font-medium text-gray-500 uppercase">Exceptions</th>
                </tr>
              </thead>
              <tbody>
                {historyData.data.map((record) => (
                  <tr key={record.id} className="border-b border-surface-border/50 hover:bg-surface-hover/50">
                    <td className="py-3 px-4 text-sm text-gray-300">
                      {new Date(record.recordDate).toLocaleDateString('en-US', {
                        month: '2-digit', day: '2-digit', year: 'numeric',
                      })}
                    </td>
                    <td className="py-3 px-4 text-sm text-gray-300">
                      <span className="flex items-center gap-1.5">
                        <Clock size={12} className="text-green-400" />
                        {record.firstPunchIn || '—'}
                      </span>
                    </td>
                    <td className="py-3 px-4 text-sm text-gray-300">
                      <span className="flex items-center gap-1.5">
                        <Clock size={12} className="text-red-400" />
                        {record.lastPunchOut || '—'}
                      </span>
                    </td>
                    <td className="py-3 px-4 text-sm text-white font-medium">
                      {Math.floor(record.totalMinutes / 60)}h {record.totalMinutes % 60}m
                    </td>
                    <td className="py-3 px-4">
                      <div className="flex flex-wrap gap-1">
                        {record.exceptions.map((exc: string) => (
                          <ExceptionBadge key={exc} type={exc as ExceptionType} />
                        ))}
                        {record.exceptions.length === 0 && (
                          <span className="text-xs text-gray-500">None</span>
                        )}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <div className="text-center py-8">
            <Calendar size={40} className="mx-auto text-gray-600 mb-3" />
            <p className="text-gray-400 text-sm">No attendance history found.</p>
          </div>
        )}
      </div>
    </div>
  );
}
