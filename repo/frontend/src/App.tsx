import { Routes, Route, Navigate } from 'react-router-dom';
import { useAuth } from './context/AuthContext';
import type { UserRole } from './types';
import Login from './pages/Login';
import Dashboard from './pages/Dashboard';
import Layout from './components/layout/Layout';
import AttendancePage from './pages/attendance/AttendancePage';
import ExceptionRequestForm from './pages/attendance/ExceptionRequestForm';
import RequestDetailPage from './pages/attendance/RequestDetailPage';
import ApprovalQueuePage from './pages/approvals/ApprovalQueuePage';
import WorkOrderListPage from './pages/workorders/WorkOrderListPage';
import WorkOrderForm from './pages/workorders/WorkOrderForm';
import WorkOrderDetailPage from './pages/workorders/WorkOrderDetailPage';
import BookingPage from './pages/bookings/BookingPage';
import UserManagementPage from './pages/admin/UserManagementPage';
import AuditLogPage from './pages/admin/AuditLogPage';
import CsvImportPage from './pages/admin/CsvImportPage';
import SystemConfigPage from './pages/admin/SystemConfigPage';

function ProtectedRoute({ children }: { children: React.ReactNode }) {
  const { isAuthenticated, isLoading } = useAuth();
  if (isLoading) {
    return (
      <div className="flex items-center justify-center min-h-screen bg-surface">
        <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-accent" />
      </div>
    );
  }
  return isAuthenticated ? <>{children}</> : <Navigate to="/login" replace />;
}

/**
 * Role-gated route guard. Defense-in-depth against users navigating to
 * privileged UI routes they are not allowed to use. Backend denial still
 * enforces the real access control; this just prevents dead-end pages
 * and mirrors the sidebar's role matrix.
 */
function RoleRoute({
  allow,
  children,
}: {
  allow: UserRole[];
  children: React.ReactNode;
}) {
  const { user, isLoading } = useAuth();
  if (isLoading) return null;
  if (!user || !allow.includes(user.role)) {
    return <Navigate to="/" replace />;
  }
  return <>{children}</>;
}

function App() {
  return (
    <Routes>
      <Route path="/login" element={<Login />} />
      <Route
        path="/*"
        element={
          <ProtectedRoute>
            <Layout>
              <Routes>
                <Route path="/" element={<Dashboard />} />
                <Route path="/attendance" element={<AttendancePage />} />
                <Route path="/attendance/request" element={<ExceptionRequestForm />} />
                <Route path="/attendance/request/:id" element={<RequestDetailPage />} />
                <Route
                  path="/approvals"
                  element={
                    <RoleRoute allow={['ROLE_ADMIN', 'ROLE_HR_ADMIN', 'ROLE_SUPERVISOR']}>
                      <ApprovalQueuePage />
                    </RoleRoute>
                  }
                />
                <Route path="/work-orders" element={<WorkOrderListPage />} />
                <Route path="/work-orders/new" element={<WorkOrderForm />} />
                <Route path="/work-orders/:id" element={<WorkOrderDetailPage />} />
                <Route path="/bookings" element={<BookingPage />} />
                <Route
                  path="/admin/users"
                  element={
                    <RoleRoute allow={['ROLE_ADMIN', 'ROLE_HR_ADMIN']}>
                      <UserManagementPage />
                    </RoleRoute>
                  }
                />
                <Route
                  path="/admin/audit"
                  element={
                    <RoleRoute allow={['ROLE_ADMIN']}>
                      <AuditLogPage />
                    </RoleRoute>
                  }
                />
                <Route
                  path="/admin/csv-import"
                  element={
                    <RoleRoute allow={['ROLE_ADMIN']}>
                      <CsvImportPage />
                    </RoleRoute>
                  }
                />
                <Route
                  path="/admin/config"
                  element={
                    <RoleRoute allow={['ROLE_ADMIN', 'ROLE_HR_ADMIN']}>
                      <SystemConfigPage />
                    </RoleRoute>
                  }
                />
                <Route path="*" element={<Navigate to="/" replace />} />
              </Routes>
            </Layout>
          </ProtectedRoute>
        }
      />
    </Routes>
  );
}

export default App;
