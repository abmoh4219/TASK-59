import { useAuth } from '../../context/AuthContext';
import { LogOut, Bell, ChevronRight, Search } from 'lucide-react';
import { useNavigate, useLocation } from 'react-router-dom';

function formatRoleLabel(role: string): string {
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

function roleBadgeStyle(role: string): string {
  const map: Record<string, string> = {
    ROLE_ADMIN: 'bg-yellow-500/10 text-yellow-400 border-yellow-500/30',
    ROLE_HR_ADMIN: 'bg-red-500/10 text-red-400 border-red-500/30',
    ROLE_SUPERVISOR: 'bg-purple-500/10 text-purple-400 border-purple-500/30',
    ROLE_DISPATCHER: 'bg-orange-500/10 text-orange-400 border-orange-500/30',
    ROLE_TECHNICIAN: 'bg-green-500/10 text-green-400 border-green-500/30',
    ROLE_EMPLOYEE: 'bg-blue-500/10 text-blue-400 border-blue-500/30',
  };
  return map[role] || 'bg-gray-500/10 text-gray-400 border-gray-500/30';
}

function getBreadcrumbs(pathname: string): string[] {
  if (pathname === '/' || pathname === '') return ['Dashboard'];
  const segments = pathname.split('/').filter(Boolean);
  return segments.map((s) => {
    const map: Record<string, string> = {
      attendance: 'Attendance',
      request: 'Request',
      approvals: 'Approvals',
      'work-orders': 'Work Orders',
      new: 'New',
      bookings: 'Bookings',
      admin: 'Admin',
      users: 'Users',
      audit: 'Audit Log',
      'csv-import': 'CSV Import',
      config: 'System Config',
    };
    return map[s] || s.charAt(0).toUpperCase() + s.slice(1);
  });
}

export default function TopBar() {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();

  const handleLogout = () => {
    logout();
    navigate('/login', { replace: true });
  };

  const crumbs = getBreadcrumbs(location.pathname);

  return (
    <header className="sticky top-0 z-30 h-16 bg-surface/70 backdrop-blur-2xl border-b border-surface-border/60 flex items-center justify-between px-8">
      {/* Breadcrumbs */}
      <nav className="flex items-center gap-2 text-sm">
        {crumbs.map((crumb, i) => (
          <div key={i} className="flex items-center gap-2">
            {i > 0 && <ChevronRight size={14} className="text-gray-700" strokeWidth={2.5} />}
            <span
              className={
                i === crumbs.length - 1
                  ? 'text-white font-semibold'
                  : 'text-gray-500'
              }
            >
              {crumb}
            </span>
          </div>
        ))}
      </nav>

      {/* Right side: search, notifications, user */}
      <div className="flex items-center gap-3">
        {/* Search (decorative placeholder) */}
        <div className="hidden md:flex items-center gap-2 px-3 py-1.5 rounded-lg bg-white/[0.03] border border-white/5 text-gray-500 text-xs hover:bg-white/[0.05] transition-colors cursor-pointer">
          <Search size={14} />
          <span>Search</span>
          <kbd className="ml-3 px-1.5 py-0.5 text-[10px] rounded bg-white/5 border border-white/10 font-mono">⌘K</kbd>
        </div>

        {user?.role === 'ROLE_ADMIN' && (
          <button
            className="relative p-2 text-gray-400 hover:text-white transition-colors rounded-lg hover:bg-white/5"
            title="Notifications"
          >
            <Bell size={18} />
            <span className="absolute top-1.5 right-1.5 w-2 h-2 bg-accent rounded-full ring-2 ring-surface" />
          </button>
        )}

        {user && (
          <div className="flex items-center gap-3 pl-3 ml-1 border-l border-surface-border/60">
            <div className="flex flex-col items-end leading-tight">
              <span className="text-sm font-semibold text-white">
                {user.firstName} {user.lastName}
              </span>
              <span
                className={`text-[9px] px-1.5 py-0.5 rounded-full border font-bold tracking-widest mt-0.5 ${roleBadgeStyle(
                  user.role,
                )}`}
              >
                {formatRoleLabel(user.role).toUpperCase()}
              </span>
            </div>
            <div className="relative">
              <div className="w-9 h-9 rounded-xl bg-gradient-accent flex items-center justify-center text-white text-xs font-bold shadow-glow">
                {user.firstName?.[0]}
                {user.lastName?.[0]}
              </div>
              <div className="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 bg-green-400 rounded-full border-2 border-surface" />
            </div>
            <button
              onClick={handleLogout}
              className="p-2 text-gray-400 hover:text-red-400 transition-colors rounded-lg hover:bg-red-500/10"
              title="Sign out"
            >
              <LogOut size={18} />
            </button>
          </div>
        )}
      </div>
    </header>
  );
}
