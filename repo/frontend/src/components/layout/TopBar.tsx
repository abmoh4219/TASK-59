import { useAuth } from '../../context/AuthContext';
import { LogOut, Bell, ChevronRight } from 'lucide-react';
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

function roleBadgeColor(role: string): string {
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
    // Decode common segment names
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

  const handleLogout = async () => {
    await logout();
    navigate('/login');
  };

  const crumbs = getBreadcrumbs(location.pathname);

  return (
    <header className="sticky top-0 z-30 h-16 bg-surface/80 backdrop-blur-md border-b border-surface-border flex items-center justify-between px-6">
      {/* Breadcrumbs */}
      <nav className="flex items-center gap-2 text-sm">
        {crumbs.map((crumb, i) => (
          <div key={i} className="flex items-center gap-2">
            {i > 0 && <ChevronRight size={14} className="text-gray-600" />}
            <span
              className={
                i === crumbs.length - 1
                  ? 'text-white font-medium'
                  : 'text-gray-500'
              }
            >
              {crumb}
            </span>
          </div>
        ))}
      </nav>

      {/* Right side: notifications + user */}
      <div className="flex items-center gap-4">
        {user?.role === 'ROLE_ADMIN' && (
          <button
            className="relative p-2 text-gray-400 hover:text-white transition-colors rounded-lg hover:bg-surface-hover"
            title="Notifications"
          >
            <Bell size={18} />
          </button>
        )}

        {user && (
          <div className="flex items-center gap-3 pl-4 border-l border-surface-border">
            <div className="flex flex-col items-end">
              <span className="text-sm font-medium text-white">
                {user.firstName} {user.lastName}
              </span>
              <span
                className={`text-[10px] px-1.5 py-0.5 rounded-full border font-semibold tracking-wide ${roleBadgeColor(
                  user.role,
                )}`}
              >
                {formatRoleLabel(user.role).toUpperCase()}
              </span>
            </div>
            <div className="w-9 h-9 rounded-full bg-accent/20 flex items-center justify-center text-accent-light text-sm font-semibold">
              {user.firstName?.[0]}
              {user.lastName?.[0]}
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
