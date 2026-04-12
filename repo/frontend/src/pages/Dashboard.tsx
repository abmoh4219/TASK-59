import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { getTodayAttendance, getMyRequests, getApprovalQueue } from '../api/attendance';
import { getWorkOrders } from '../api/workOrders';
import apiClient from '../api/client';
import type { ExceptionType, WorkOrder } from '../types';
import {
  Clock, CheckSquare, Wrench, AlertTriangle, Users,
  FileWarning, ArrowRight, Activity, Sparkles,
} from 'lucide-react';

interface StatCardProps {
  icon: React.ReactNode;
  label: string;
  value: string | number;
  link?: string;
  tone?: 'accent' | 'red' | 'amber' | 'green';
}

function StatCard({ icon, label, value, link, tone = 'accent' }: StatCardProps) {
  const toneMap: Record<string, { bg: string; text: string; glow: string; border: string }> = {
    accent: {
      bg: 'bg-gradient-to-br from-accent/20 to-accent-dark/10',
      text: 'text-accent-light',
      glow: 'from-accent/10',
      border: 'hover:border-accent/40',
    },
    red: {
      bg: 'bg-gradient-to-br from-red-500/20 to-red-600/10',
      text: 'text-red-400',
      glow: 'from-red-500/10',
      border: 'hover:border-red-500/40',
    },
    amber: {
      bg: 'bg-gradient-to-br from-amber-500/20 to-orange-500/10',
      text: 'text-amber-400',
      glow: 'from-amber-500/10',
      border: 'hover:border-amber-500/40',
    },
    green: {
      bg: 'bg-gradient-to-br from-green-500/20 to-emerald-600/10',
      text: 'text-green-400',
      glow: 'from-green-500/10',
      border: 'hover:border-green-500/40',
    },
  };
  const colors = toneMap[tone];

  const content = (
    <div className={`group relative premium-card premium-card-hover p-6 overflow-hidden ${colors.border}`}>
      {/* Subtle glow on hover */}
      <div
        className={`absolute -top-20 -right-20 w-40 h-40 bg-gradient-to-br ${colors.glow} to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500 pointer-events-none rounded-full blur-3xl`}
      />

      <div className="relative flex items-start justify-between mb-4">
        <div className={`w-11 h-11 rounded-xl ${colors.bg} ${colors.text} flex items-center justify-center border border-white/10 shadow-inner-glow`}>
          {icon}
        </div>
        {link && (
          <div className="text-gray-600 group-hover:text-accent-light group-hover:translate-x-0.5 transition-all">
            <ArrowRight size={16} />
          </div>
        )}
      </div>
      <div className="relative">
        <p className="text-[11px] text-gray-500 uppercase tracking-wider font-semibold mb-1.5">{label}</p>
        <p className="text-3xl font-bold text-white tracking-tight">{value}</p>
      </div>
    </div>
  );
  return link ? <Link to={link} className="block">{content}</Link> : content;
}

function EmployeeDashboard() {
  const { data: attendance } = useQuery({
    queryKey: ['attendance', 'today'],
    queryFn: getTodayAttendance,
  });

  const { data: requests } = useQuery({
    queryKey: ['requests'],
    queryFn: getMyRequests,
  });

  const { data: workOrders } = useQuery({
    queryKey: ['work-orders'],
    queryFn: () => getWorkOrders(),
  });

  const exceptions = (attendance?.exceptions || []) as ExceptionType[];
  const pendingRequests = (requests || []).filter((r) => r.status === 'PENDING').length;
  const recentWorkOrders = (workOrders?.data || []).slice(0, 3);

  return (
    <div className="space-y-4">
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <StatCard
          icon={<Clock size={20} />}
          label="Today's Status"
          value={exceptions.length > 0 ? `${exceptions.length} exception(s)` : 'On track'}
          link="/attendance"
          tone={exceptions.length > 0 ? 'amber' : 'green'}
        />
        <StatCard
          icon={<FileWarning size={20} />}
          label="Pending Requests"
          value={pendingRequests}
          link="/attendance"
        />
        <StatCard
          icon={<Wrench size={20} />}
          label="My Work Orders"
          value={workOrders?.total || 0}
          link="/work-orders"
        />
      </div>

      {recentWorkOrders.length > 0 && (
        <div className="bg-surface-card border border-surface-border rounded-xl p-5">
          <h3 className="text-sm font-semibold text-white mb-3">Recent Work Orders</h3>
          <div className="space-y-2">
            {recentWorkOrders.map((wo: WorkOrder) => (
              <Link
                key={wo.id}
                to={`/work-orders/${wo.id}`}
                className="flex items-center justify-between p-3 rounded-lg bg-surface hover:bg-surface-hover transition"
              >
                <div>
                  <p className="text-sm text-white">{wo.category} — {wo.building}</p>
                  <p className="text-xs text-gray-500 line-clamp-1">{wo.description.slice(0, 60)}</p>
                </div>
                <span className="text-xs text-gray-400">{wo.status}</span>
              </Link>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}

function SupervisorDashboard() {
  const { data: queue } = useQuery({
    queryKey: ['approval-queue'],
    queryFn: getApprovalQueue,
  });

  const list = (queue as Array<{ isOverdue?: boolean }> | undefined) || [];
  const pendingCount = list.length;
  const overdueCount = list.filter((q) => q.isOverdue).length;

  return (
    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
      <StatCard icon={<CheckSquare size={20} />} label="Pending Approvals" value={pendingCount} link="/approvals" />
      <StatCard icon={<AlertTriangle size={20} />} label="Overdue" value={overdueCount} link="/approvals" tone="red" />
      <StatCard icon={<Clock size={20} />} label="My Attendance" value="View" link="/attendance" />
    </div>
  );
}

function HrAdminDashboard() {
  const { data: queue } = useQuery({
    queryKey: ['approval-queue'],
    queryFn: getApprovalQueue,
  });

  const { data: anomalies } = useQuery<Array<{ id: number }>>({
    queryKey: ['anomaly-alerts'],
    queryFn: async () => {
      const res = await apiClient.get('/admin/anomaly-alerts');
      return res.data;
    },
  });

  const { data: users } = useQuery<Array<{ id: number }>>({
    queryKey: ['admin-users'],
    queryFn: async () => {
      const res = await apiClient.get('/admin/users');
      return res.data;
    },
  });

  return (
    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
      <StatCard icon={<CheckSquare size={20} />} label="Pending Approvals" value={(queue || []).length} link="/approvals" />
      <StatCard icon={<AlertTriangle size={20} />} label="Security Alerts (24h)" value={(anomalies || []).length} tone="red" />
      <StatCard icon={<Users size={20} />} label="Total Users" value={(users || []).length} link="/admin/users" />
    </div>
  );
}

function DispatcherDashboard() {
  const { data: workOrders } = useQuery({
    queryKey: ['work-orders'],
    queryFn: () => getWorkOrders(),
  });

  const list = workOrders?.data || [];
  const submitted = list.filter((w: WorkOrder) => w.status === 'submitted').length;
  const urgent = list.filter((w: WorkOrder) => w.priority === 'URGENT').length;

  return (
    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
      <StatCard icon={<FileWarning size={20} />} label="Unassigned" value={submitted} link="/work-orders" tone="amber" />
      <StatCard icon={<AlertTriangle size={20} />} label="Urgent" value={urgent} link="/work-orders" tone="red" />
      <StatCard icon={<Wrench size={20} />} label="Total Orders" value={list.length} link="/work-orders" />
    </div>
  );
}

function TechnicianDashboard() {
  const { data: workOrders } = useQuery({
    queryKey: ['work-orders'],
    queryFn: () => getWorkOrders(),
  });

  const list = workOrders?.data || [];
  const inProgress = list.filter((w: WorkOrder) => w.status === 'in_progress').length;
  const dispatched = list.filter((w: WorkOrder) => w.status === 'dispatched').length;

  return (
    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
      <StatCard icon={<FileWarning size={20} />} label="Awaiting Accept" value={dispatched} link="/work-orders" tone="amber" />
      <StatCard icon={<Activity size={20} />} label="In Progress" value={inProgress} link="/work-orders" />
      <StatCard icon={<Wrench size={20} />} label="Total Assigned" value={list.length} link="/work-orders" />
    </div>
  );
}

function AdminDashboard() {
  const { data: users } = useQuery<Array<{ id: number }>>({
    queryKey: ['admin-users'],
    queryFn: async () => {
      const res = await apiClient.get('/admin/users');
      return res.data;
    },
  });

  const { data: anomalies } = useQuery<Array<{ id: number }>>({
    queryKey: ['anomaly-alerts'],
    queryFn: async () => {
      const res = await apiClient.get('/admin/anomaly-alerts');
      return res.data;
    },
  });

  const { data: workOrders } = useQuery({
    queryKey: ['work-orders'],
    queryFn: () => getWorkOrders(),
  });

  const { data: queue } = useQuery({
    queryKey: ['approval-queue'],
    queryFn: getApprovalQueue,
  });

  return (
    <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
      <StatCard icon={<Users size={20} />} label="Total Users" value={(users || []).length} link="/admin/users" />
      <StatCard icon={<CheckSquare size={20} />} label="Pending Approvals" value={(queue || []).length} link="/approvals" />
      <StatCard icon={<Wrench size={20} />} label="Open Work Orders" value={workOrders?.total || 0} link="/work-orders" />
      <StatCard icon={<AlertTriangle size={20} />} label="Security Alerts" value={(anomalies || []).length} tone="red" link="/admin/config" />
    </div>
  );
}

export default function Dashboard() {
  const { user } = useAuth();

  if (!user) return null;

  let dashboard: React.ReactNode;
  const roleLabels: Record<string, string> = {
    ROLE_ADMIN: 'Administrator',
    ROLE_HR_ADMIN: 'HR Admin',
    ROLE_SUPERVISOR: 'Supervisor',
    ROLE_DISPATCHER: 'Dispatcher',
    ROLE_TECHNICIAN: 'Technician',
    ROLE_EMPLOYEE: 'Employee',
  };

  switch (user.role) {
    case 'ROLE_ADMIN':
      dashboard = <AdminDashboard />;
      break;
    case 'ROLE_HR_ADMIN':
      dashboard = <HrAdminDashboard />;
      break;
    case 'ROLE_SUPERVISOR':
      dashboard = <SupervisorDashboard />;
      break;
    case 'ROLE_DISPATCHER':
      dashboard = <DispatcherDashboard />;
      break;
    case 'ROLE_TECHNICIAN':
      dashboard = <TechnicianDashboard />;
      break;
    default:
      dashboard = <EmployeeDashboard />;
  }

  return (
    <div className="space-y-8 animate-slide-up">
      <div className="flex items-start justify-between">
        <div>
          <div className="flex items-center gap-2 mb-2">
            <Sparkles size={14} className="text-accent-light" />
            <span className="text-xs font-semibold text-accent-light uppercase tracking-widest">
              {roleLabels[user.role]} Dashboard
            </span>
          </div>
          <h1 className="text-4xl font-bold tracking-tight">
            <span className="text-white">Welcome back, </span>
            <span className="text-gradient">{user.firstName}</span>
          </h1>
          <p className="text-sm text-gray-500 mt-2">
            {new Date().toLocaleDateString('en-US', {
              weekday: 'long', month: 'long', day: 'numeric', year: 'numeric',
            })}
          </p>
        </div>
      </div>

      {dashboard}
    </div>
  );
}
