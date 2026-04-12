import { useAuth } from '../../context/AuthContext';
import { LogOut, Bell } from 'lucide-react';
import { useNavigate } from 'react-router-dom';

export default function TopBar() {
  const { user, logout } = useAuth();
  const navigate = useNavigate();

  const handleLogout = async () => {
    await logout();
    navigate('/login');
  };

  return (
    <header className="sticky top-0 z-30 h-16 bg-surface/80 backdrop-blur-md border-b border-surface-border flex items-center justify-between px-6">
      <div className="text-sm text-gray-400">
        {/* Breadcrumbs will be added later */}
      </div>
      <div className="flex items-center gap-4">
        {user?.role === 'ROLE_ADMIN' && (
          <button className="relative p-2 text-gray-400 hover:text-white transition-colors">
            <Bell size={18} />
          </button>
        )}
        <div className="flex items-center gap-3">
          <span className="text-sm text-gray-300">{user?.firstName} {user?.lastName}</span>
          <button
            onClick={handleLogout}
            className="p-2 text-gray-400 hover:text-red-400 transition-colors"
            title="Sign out"
          >
            <LogOut size={18} />
          </button>
        </div>
      </div>
    </header>
  );
}
