import { NavLink } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext';
import type { UserRole } from '../../types';
import {
  LayoutDashboard,
  Clock,
  CheckSquare,
  Wrench,
  CalendarRange,
  Users,
  FileText,
  Upload,
  Settings,
  Shield,
} from 'lucide-react';

interface NavItem {
  label: string;
  path: string;
  icon: React.ReactNode;
  roles: UserRole[];
  children?: NavItem[];
}

const navItems: NavItem[] = [
  {
    label: 'Dashboard',
    path: '/',
    icon: <LayoutDashboard size={20} />,
    roles: ['ROLE_ADMIN', 'ROLE_HR_ADMIN', 'ROLE_SUPERVISOR', 'ROLE_DISPATCHER', 'ROLE_TECHNICIAN', 'ROLE_EMPLOYEE'],
  },
  {
    label: 'Attendance',
    path: '/attendance',
    icon: <Clock size={20} />,
    roles: ['ROLE_ADMIN', 'ROLE_HR_ADMIN', 'ROLE_SUPERVISOR', 'ROLE_EMPLOYEE'],
  },
  {
    label: 'Approvals',
    path: '/approvals',
    icon: <CheckSquare size={20} />,
    roles: ['ROLE_ADMIN', 'ROLE_HR_ADMIN', 'ROLE_SUPERVISOR'],
  },
  {
    label: 'Work Orders',
    path: '/work-orders',
    icon: <Wrench size={20} />,
    roles: ['ROLE_ADMIN', 'ROLE_DISPATCHER', 'ROLE_TECHNICIAN', 'ROLE_EMPLOYEE'],
  },
  {
    label: 'Bookings',
    path: '/bookings',
    icon: <CalendarRange size={20} />,
    roles: ['ROLE_ADMIN', 'ROLE_EMPLOYEE'],
  },
  {
    label: 'Users',
    path: '/admin/users',
    icon: <Users size={20} />,
    roles: ['ROLE_ADMIN', 'ROLE_HR_ADMIN'],
  },
  {
    label: 'Audit Log',
    path: '/admin/audit',
    icon: <Shield size={20} />,
    roles: ['ROLE_ADMIN'],
  },
  {
    label: 'CSV Import',
    path: '/admin/csv-import',
    icon: <Upload size={20} />,
    roles: ['ROLE_ADMIN'],
  },
  {
    label: 'System Config',
    path: '/admin/config',
    icon: <Settings size={20} />,
    roles: ['ROLE_ADMIN', 'ROLE_HR_ADMIN'],
  },
];

export default function Sidebar() {
  const { user } = useAuth();

  if (!user) return null;

  // Filter nav items to only show those permitted for the current role
  const visibleItems = navItems.filter((item) => {
    if (user.role === 'ROLE_ADMIN') return true;
    return item.roles.includes(user.role);
  });

  return (
    <aside className="fixed left-0 top-0 h-screen w-60 bg-gradient-to-b from-[#0D0F14] to-[#131620] border-r border-surface-border flex flex-col z-40">
      {/* Logo */}
      <div className="h-16 flex items-center px-5 border-b border-surface-border">
        <div className="flex items-center gap-2.5">
          <div className="w-8 h-8 rounded-lg bg-accent flex items-center justify-center">
            <FileText size={16} className="text-white" />
          </div>
          <div>
            <span className="text-sm font-semibold text-white tracking-tight">Workforce Hub</span>
          </div>
        </div>
      </div>

      {/* Navigation */}
      <nav className="flex-1 py-4 px-3 overflow-y-auto">
        <ul className="space-y-1">
          {visibleItems.map((item) => (
            <li key={item.path}>
              <NavLink
                to={item.path}
                end={item.path === '/'}
                className={({ isActive }) =>
                  `flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-200 ${
                    isActive
                      ? 'bg-accent/10 text-accent-light shadow-glow'
                      : 'text-gray-400 hover:text-white hover:bg-surface-hover'
                  }`
                }
              >
                {item.icon}
                {item.label}
              </NavLink>
            </li>
          ))}
        </ul>
      </nav>

      {/* User info at bottom */}
      <div className="p-4 border-t border-surface-border">
        <div className="flex items-center gap-3">
          <div className="w-8 h-8 rounded-full bg-accent/20 flex items-center justify-center text-accent text-xs font-semibold">
            {user.firstName?.[0]}
            {user.lastName?.[0]}
          </div>
          <div className="flex-1 min-w-0">
            <p className="text-sm font-medium text-white truncate">
              {user.firstName} {user.lastName}
            </p>
            <p className="text-xs text-gray-500 truncate">{formatRole(user.role)}</p>
          </div>
        </div>
      </div>
    </aside>
  );
}

function formatRole(role: string): string {
  const map: Record<string, string> = {
    ROLE_ADMIN: 'Administrator',
    ROLE_HR_ADMIN: 'HR Admin',
    ROLE_SUPERVISOR: 'Supervisor',
    ROLE_DISPATCHER: 'Dispatcher',
    ROLE_TECHNICIAN: 'Technician',
    ROLE_EMPLOYEE: 'Employee',
  };
  return map[role] || role;
}
