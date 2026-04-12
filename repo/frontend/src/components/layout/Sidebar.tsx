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
  Upload,
  Settings,
  ShieldCheck,
  FileSearch,
} from 'lucide-react';

interface NavItem {
  label: string;
  path: string;
  icon: React.ReactNode;
  roles: UserRole[];
  section?: string;
}

const navItems: NavItem[] = [
  {
    label: 'Dashboard',
    path: '/',
    icon: <LayoutDashboard size={18} strokeWidth={2} />,
    roles: ['ROLE_ADMIN', 'ROLE_HR_ADMIN', 'ROLE_SUPERVISOR', 'ROLE_DISPATCHER', 'ROLE_TECHNICIAN', 'ROLE_EMPLOYEE'],
  },
  {
    label: 'Attendance',
    path: '/attendance',
    icon: <Clock size={18} strokeWidth={2} />,
    roles: ['ROLE_ADMIN', 'ROLE_HR_ADMIN', 'ROLE_SUPERVISOR', 'ROLE_EMPLOYEE'],
  },
  {
    label: 'Approvals',
    path: '/approvals',
    icon: <CheckSquare size={18} strokeWidth={2} />,
    roles: ['ROLE_ADMIN', 'ROLE_HR_ADMIN', 'ROLE_SUPERVISOR'],
  },
  {
    label: 'Work Orders',
    path: '/work-orders',
    icon: <Wrench size={18} strokeWidth={2} />,
    roles: ['ROLE_ADMIN', 'ROLE_DISPATCHER', 'ROLE_TECHNICIAN', 'ROLE_EMPLOYEE'],
  },
  {
    label: 'Bookings',
    path: '/bookings',
    icon: <CalendarRange size={18} strokeWidth={2} />,
    roles: ['ROLE_ADMIN', 'ROLE_EMPLOYEE'],
  },
  // Admin section
  {
    label: 'Users',
    path: '/admin/users',
    icon: <Users size={18} strokeWidth={2} />,
    roles: ['ROLE_ADMIN', 'ROLE_HR_ADMIN'],
    section: 'Administration',
  },
  {
    label: 'Audit Log',
    path: '/admin/audit',
    icon: <FileSearch size={18} strokeWidth={2} />,
    roles: ['ROLE_ADMIN'],
    section: 'Administration',
  },
  {
    label: 'CSV Import',
    path: '/admin/csv-import',
    icon: <Upload size={18} strokeWidth={2} />,
    roles: ['ROLE_ADMIN'],
    section: 'Administration',
  },
  {
    label: 'System Config',
    path: '/admin/config',
    icon: <Settings size={18} strokeWidth={2} />,
    roles: ['ROLE_ADMIN', 'ROLE_HR_ADMIN'],
    section: 'Administration',
  },
];

export default function Sidebar() {
  const { user } = useAuth();

  if (!user) return null;

  const visibleItems = navItems.filter((item) => {
    if (user.role === 'ROLE_ADMIN') return true;
    return item.roles.includes(user.role);
  });

  // Group by section
  const mainItems = visibleItems.filter((i) => !i.section);
  const sectionedItems = visibleItems.filter((i) => i.section);

  return (
    <aside className="fixed left-0 top-0 h-screen w-64 bg-gradient-sidebar border-r border-surface-border/60 flex flex-col z-40 backdrop-blur-xl">
      {/* Logo + wordmark */}
      <div className="h-16 flex items-center px-5 border-b border-surface-border/60">
        <div className="flex items-center gap-3">
          <div className="relative">
            <div className="absolute inset-0 bg-gradient-accent rounded-xl blur-md opacity-60" />
            <div className="relative w-9 h-9 rounded-xl bg-gradient-accent flex items-center justify-center shadow-glow">
              <ShieldCheck size={18} className="text-white" strokeWidth={2.5} />
            </div>
          </div>
          <div>
            <div className="text-sm font-bold text-white tracking-tight">Workforce Hub</div>
            <div className="text-[10px] text-gray-500 uppercase tracking-widest">Operations</div>
          </div>
        </div>
      </div>

      {/* Navigation */}
      <nav className="flex-1 py-6 px-3 overflow-y-auto">
        {/* Main section */}
        <div className="mb-6">
          <div className="px-3 mb-2">
            <p className="text-[10px] font-semibold text-gray-600 uppercase tracking-widest">Workspace</p>
          </div>
          <ul className="space-y-1">
            {mainItems.map((item) => (
              <li key={item.path}>
                <NavLink
                  to={item.path}
                  end={item.path === '/'}
                  className={({ isActive }) =>
                    `group relative flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-200 ${
                      isActive
                        ? 'text-white bg-gradient-to-r from-accent/20 to-accent/5 border border-accent/20'
                        : 'text-gray-400 hover:text-white hover:bg-white/5'
                    }`
                  }
                >
                  {({ isActive }) => (
                    <>
                      {isActive && (
                        <span className="absolute left-0 top-1/2 -translate-y-1/2 h-6 w-1 bg-gradient-accent rounded-r-full shadow-glow" />
                      )}
                      <span className={isActive ? 'text-accent-light' : ''}>{item.icon}</span>
                      {item.label}
                    </>
                  )}
                </NavLink>
              </li>
            ))}
          </ul>
        </div>

        {/* Admin section */}
        {sectionedItems.length > 0 && (
          <div>
            <div className="px-3 mb-2">
              <p className="text-[10px] font-semibold text-gray-600 uppercase tracking-widest">Administration</p>
            </div>
            <ul className="space-y-1">
              {sectionedItems.map((item) => (
                <li key={item.path}>
                  <NavLink
                    to={item.path}
                    className={({ isActive }) =>
                      `group relative flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-200 ${
                        isActive
                          ? 'text-white bg-gradient-to-r from-accent/20 to-accent/5 border border-accent/20'
                          : 'text-gray-400 hover:text-white hover:bg-white/5'
                      }`
                    }
                  >
                    {({ isActive }) => (
                      <>
                        {isActive && (
                          <span className="absolute left-0 top-1/2 -translate-y-1/2 h-6 w-1 bg-gradient-accent rounded-r-full shadow-glow" />
                        )}
                        <span className={isActive ? 'text-accent-light' : ''}>{item.icon}</span>
                        {item.label}
                      </>
                    )}
                  </NavLink>
                </li>
              ))}
            </ul>
          </div>
        )}
      </nav>

      {/* User card at bottom */}
      <div className="p-3 border-t border-surface-border/60">
        <div className="flex items-center gap-3 p-3 rounded-xl bg-white/[0.02] border border-white/5 hover:bg-white/[0.04] transition-colors">
          <div className="relative">
            <div className="w-9 h-9 rounded-xl bg-gradient-accent flex items-center justify-center text-white text-xs font-bold shadow-glow">
              {user.firstName?.[0]}
              {user.lastName?.[0]}
            </div>
            <div className="absolute -bottom-0.5 -right-0.5 w-3 h-3 bg-green-400 rounded-full border-2 border-surface" />
          </div>
          <div className="flex-1 min-w-0">
            <p className="text-sm font-semibold text-white truncate">
              {user.firstName} {user.lastName}
            </p>
            <p className="text-[11px] text-gray-500 truncate">{formatRole(user.role)}</p>
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
