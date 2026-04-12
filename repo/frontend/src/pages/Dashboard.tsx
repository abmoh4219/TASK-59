import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { getTodayAttendance, getMyRequests, getApprovalQueue } from '../api/attendance';
import { getWorkOrders } from '../api/workOrders';
import apiClient from '../api/client';
import type { ExceptionType, WorkOrder } from '../types';
import {
  Clock, CheckSquare, Wrench, AlertTriangle, Users,
  TrendingUp, FileWarning, ArrowRight, Activity,
} from 'lucide-react';

interface StatCardProps {
  icon: React.ReactNode;
  label: string;
  value: string | number;
  link?: string;
  tone?: 'accent' | 'red' | 'amber' | 'green';
}

function StatCard({ icon, label, value, link, tone = 'accent' }: StatCardProps) {
  const toneMap: Record<string, { bg: string; text: string }> = {
    accent: { bg: 'bg-accent/10', text: 'text-accent-light' },
    red: { bg: 'bg-red-500/10', text: 'text-red-400' },
    amber: { bg: 'bg-amber-500/10', text: 'text-amber-400' },
    green: { bg: 'bg-green-500/10', text: 'text-green-400' },
  };
  const colors = toneMap[tone];

  const content = (
    <div className="bg-surface-card border border-surface-border rounded-xl p-5 hover:border-accent/40 hover:shadow-glow transition-all">
      <div className="flex items-start justify-between mb-3">
        <div className={`w-10 h-10 rounded-lg ${colors.bg} ${colors.text} flex items-center justify-center`}>
          {icon}
        </div>
      </div>
      <div>
        <p className="text-xs text-gray-500 uppercase tracking-wide mb-1">{label}</p>
        <p className="text-2xl font-bold text-white">{value}</p>
      </div>
      {link && (
        <div className="mt-3 flex items-center gap-1 text-xs text-accent-light">
          View <ArrowRight size={12} />
        </div>
      )}
    </div>
  );
  return link ? <Link to={link}>{content}</Link> : content;
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
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-white">
          Welcome back, {user.firstName}
        </h1>
        <p className="text-sm text-gray-400 mt-1">
          {roleLabels[user.role]} &middot; {new Date().toLocaleDateString('en-US', {
            weekday: 'long', month: 'long', day: 'numeric', year: 'numeric',
          })}
        </p>
      </div>

      {dashboard}

      <div className="bg-surface-card border border-surface-border rounded-xl p-5">
        <h3 className="text-sm font-semibold text-white mb-3 flex items-center gap-2">
          <TrendingUp size={16} className="text-accent-light" />
          System Health
        </h3>
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
          <div>
            <p className="text-xs text-gray-500">Backend</p>
            <p className="text-green-400 font-medium">● Operational</p>
          </div>
          <div>
            <p className="text-xs text-gray-500">Database</p>
            <p className="text-green-400 font-medium">● Connected</p>
          </div>
          <div>
            <p className="text-xs text-gray-500">Auth</p>
            <p className="text-green-400 font-medium">● Active</p>
          </div>
          <div>
            <p className="text-xs text-gray-500">Session</p>
            <p className="text-green-400 font-medium">● Secure</p>
          </div>
        </div>
      </div>
    </div>
  );
}
