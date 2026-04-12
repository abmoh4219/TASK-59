import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  Users,
  Plus,
  Pencil,
  Trash2,
  AlertCircle,
  Loader2,
  UserX,
  CheckCircle2,
  X,
  Eye,
  EyeOff,
  RefreshCw,
} from 'lucide-react';
import apiClient from '../../api/client';
import type { UserRole } from '../../types';

// ── Types ──────────────────────────────────────────────────────────────────────

interface AdminUser {
  id: number;
  username: string;
  email: string;
  firstName: string;
  lastName: string;
  role: UserRole;
  phone: string;
  isActive: boolean;
  isOut: boolean;
  deletedAt: string | null;
}

interface CreateUserPayload {
  username: string;
  email: string;
  password: string;
  firstName: string;
  lastName: string;
  role: UserRole;
  phone: string;
}

type EditUserPayload = Partial<Omit<CreateUserPayload, 'password'>>;

// ── API ────────────────────────────────────────────────────────────────────────

const fetchUsers = async (): Promise<AdminUser[]> => {
  const res = await apiClient.get<AdminUser[]>('/admin/users');
  return res.data;
};

const createUser = async (payload: CreateUserPayload): Promise<AdminUser> => {
  const res = await apiClient.post<AdminUser>('/admin/users', payload);
  return res.data;
};

const updateUser = async (id: number, payload: EditUserPayload): Promise<AdminUser> => {
  const res = await apiClient.put<AdminUser>(`/admin/users/${id}`, payload);
  return res.data;
};

const deleteUserData = async (id: number): Promise<void> => {
  await apiClient.post(`/admin/users/${id}/delete-data`);
};

// ── Helpers ────────────────────────────────────────────────────────────────────

const ROLES: { value: UserRole; label: string; badgeClass: string }[] = [
  { value: 'ROLE_ADMIN', label: 'System Administrator', badgeClass: 'bg-yellow-500/15 text-yellow-300 border-yellow-500/25' },
  { value: 'ROLE_HR_ADMIN', label: 'HR Admin', badgeClass: 'bg-red-500/15 text-red-300 border-red-500/25' },
  { value: 'ROLE_SUPERVISOR', label: 'Supervisor', badgeClass: 'bg-purple-500/15 text-purple-300 border-purple-500/25' },
  { value: 'ROLE_EMPLOYEE', label: 'Employee', badgeClass: 'bg-blue-500/15 text-blue-300 border-blue-500/25' },
  { value: 'ROLE_DISPATCHER', label: 'Dispatcher', badgeClass: 'bg-orange-500/15 text-orange-300 border-orange-500/25' },
  { value: 'ROLE_TECHNICIAN', label: 'Technician', badgeClass: 'bg-green-500/15 text-green-300 border-green-500/25' },
];

function roleBadgeClass(role: UserRole): string {
  return ROLES.find((r) => r.value === role)?.badgeClass
    ?? 'bg-gray-500/15 text-gray-300 border-gray-500/25';
}

function roleLabel(role: UserRole): string {
  return ROLES.find((r) => r.value === role)?.label ?? role;
}

function statusBadge(user: AdminUser) {
  if (user.deletedAt) {
    return (
      <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-500/15 text-gray-400 border border-gray-500/25">
        Anonymized
      </span>
    );
  }
  if (!user.isActive) {
    return (
      <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-500/15 text-red-300 border border-red-500/25">
        Inactive
      </span>
    );
  }
  return (
    <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-500/15 text-green-300 border border-green-500/25">
      Active
    </span>
  );
}

// ── Shared field styles ────────────────────────────────────────────────────────

const inputClass =
  'w-full bg-surface border border-surface-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-2 focus:ring-accent focus:border-transparent placeholder-gray-500 transition-colors';
const selectClass =
  'w-full bg-surface border border-surface-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-2 focus:ring-accent focus:border-transparent transition-colors appearance-none';

function FieldLabel({ children }: { children: React.ReactNode }) {
  return (
    <label className="block text-xs font-medium text-gray-400 mb-1">
      {children}
    </label>
  );
}

// ── Skeleton ───────────────────────────────────────────────────────────────────

function TableSkeleton() {
  return (
    <div className="space-y-2 animate-pulse">
      {Array.from({ length: 6 }).map((_, i) => (
        <div key={i} className="h-14 w-full rounded-lg bg-surface-hover" />
      ))}
    </div>
  );
}

// ── Create / Edit Modal ────────────────────────────────────────────────────────

type ModalMode = 'create' | 'edit';

interface UserFormModalProps {
  mode: ModalMode;
  user?: AdminUser;
  onClose: () => void;
  onSuccess: () => void;
}

function UserFormModal({ mode, user, onClose, onSuccess }: UserFormModalProps) {
  const isEdit = mode === 'edit';

  const [form, setForm] = useState({
    username: user?.username ?? '',
    email: user?.email ?? '',
    password: '',
    firstName: user?.firstName ?? '',
    lastName: user?.lastName ?? '',
    role: (user?.role ?? 'ROLE_EMPLOYEE') as UserRole,
    phone: user?.phone ?? '',
  });
  const [showPassword, setShowPassword] = useState(false);
  const [apiError, setApiError] = useState<string | null>(null);

  const mutation = useMutation({
    mutationFn: () =>
      isEdit && user
        ? updateUser(user.id, {
            username: form.username,
            email: form.email,
            firstName: form.firstName,
            lastName: form.lastName,
            role: form.role,
            phone: form.phone,
          })
        : createUser(form),
    onSuccess: () => {
      onSuccess();
      onClose();
    },
    onError: (err: unknown) => {
      const msg =
        (err as { response?: { data?: { message?: string } } })?.response?.data
          ?.message ?? (err as Error)?.message ?? 'An error occurred.';
      setApiError(msg);
    },
  });

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setApiError(null);
    mutation.mutate();
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      {/* Backdrop */}
      <div
        className="absolute inset-0 bg-black/60 backdrop-blur-sm"
        onClick={onClose}
      />
      {/* Modal */}
      <div className="relative w-full max-w-lg bg-surface-card border border-surface-border rounded-2xl shadow-glow-lg overflow-hidden">
        {/* Header */}
        <div className="flex items-center justify-between px-6 py-4 border-b border-surface-border">
          <div className="flex items-center gap-2">
            <Users size={18} className="text-accent" />
            <h2 className="text-base font-semibold text-white">
              {isEdit ? 'Edit User' : 'Create New User'}
            </h2>
          </div>
          <button
            onClick={onClose}
            className="p-1.5 text-gray-500 hover:text-gray-200 transition-colors rounded-lg hover:bg-surface-hover"
          >
            <X size={16} />
          </button>
        </div>

        {/* Body */}
        <form onSubmit={handleSubmit} className="px-6 py-5 space-y-4">
          <div className="grid grid-cols-2 gap-3">
            <div>
              <FieldLabel>First Name</FieldLabel>
              <input
                type="text"
                required
                value={form.firstName}
                onChange={(e) => setForm((f) => ({ ...f, firstName: e.target.value }))}
                className={inputClass}
                placeholder="Jane"
              />
            </div>
            <div>
              <FieldLabel>Last Name</FieldLabel>
              <input
                type="text"
                required
                value={form.lastName}
                onChange={(e) => setForm((f) => ({ ...f, lastName: e.target.value }))}
                className={inputClass}
                placeholder="Smith"
              />
            </div>
          </div>

          <div>
            <FieldLabel>Username</FieldLabel>
            <input
              type="text"
              required
              value={form.username}
              onChange={(e) => setForm((f) => ({ ...f, username: e.target.value }))}
              className={inputClass}
              placeholder="jsmith"
            />
          </div>

          <div>
            <FieldLabel>Email</FieldLabel>
            <input
              type="email"
              required
              value={form.email}
              onChange={(e) => setForm((f) => ({ ...f, email: e.target.value }))}
              className={inputClass}
              placeholder="jsmith@company.com"
            />
          </div>

          {!isEdit && (
            <div>
              <FieldLabel>Password</FieldLabel>
              <div className="relative">
                <input
                  type={showPassword ? 'text' : 'password'}
                  required
                  value={form.password}
                  onChange={(e) => setForm((f) => ({ ...f, password: e.target.value }))}
                  className={`${inputClass} pr-10`}
                  placeholder="Minimum 8 characters"
                  minLength={8}
                />
                <button
                  type="button"
                  onClick={() => setShowPassword((v) => !v)}
                  className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-300 transition-colors"
                >
                  {showPassword ? <EyeOff size={15} /> : <Eye size={15} />}
                </button>
              </div>
            </div>
          )}

          <div>
            <FieldLabel>Role</FieldLabel>
            <select
              value={form.role}
              onChange={(e) => setForm((f) => ({ ...f, role: e.target.value as UserRole }))}
              className={selectClass}
            >
              {ROLES.map((r) => (
                <option key={r.value} value={r.value}>
                  {r.label}
                </option>
              ))}
            </select>
          </div>

          <div>
            <FieldLabel>Phone</FieldLabel>
            <input
              type="tel"
              value={form.phone}
              onChange={(e) => setForm((f) => ({ ...f, phone: e.target.value }))}
              className={inputClass}
              placeholder="+15551234567"
            />
          </div>

          {apiError && (
            <div className="flex items-start gap-2 p-3 rounded-lg bg-red-500/10 border border-red-500/20">
              <AlertCircle size={14} className="text-red-400 mt-0.5 flex-shrink-0" />
              <p className="text-xs text-red-300">{apiError}</p>
            </div>
          )}

          <div className="flex items-center justify-end gap-2 pt-1">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 text-sm text-gray-400 hover:text-white transition-colors"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={mutation.isPending}
              className="inline-flex items-center gap-2 px-5 py-2 bg-accent hover:bg-accent-hover disabled:opacity-60 text-white text-sm font-medium rounded-lg transition-colors"
            >
              {mutation.isPending && <Loader2 size={14} className="animate-spin" />}
              {isEdit ? 'Save Changes' : 'Create User'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

// ── Delete Data Confirm Modal ──────────────────────────────────────────────────

interface DeleteDataModalProps {
  user: AdminUser;
  onClose: () => void;
  onSuccess: () => void;
}

function DeleteDataModal({ user, onClose, onSuccess }: DeleteDataModalProps) {
  const [confirmed, setConfirmed] = useState(false);
  const [apiError, setApiError] = useState<string | null>(null);

  const mutation = useMutation({
    mutationFn: () => deleteUserData(user.id),
    onSuccess: () => {
      onSuccess();
      onClose();
    },
    onError: (err: unknown) => {
      const msg =
        (err as { response?: { data?: { message?: string } } })?.response?.data
          ?.message ?? (err as Error)?.message ?? 'An error occurred.';
      setApiError(msg);
    },
  });

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div
        className="absolute inset-0 bg-black/70 backdrop-blur-sm"
        onClick={onClose}
      />
      <div className="relative w-full max-w-md bg-surface-card border border-red-500/30 rounded-2xl shadow-glow-lg overflow-hidden">
        {/* Header */}
        <div className="flex items-center gap-3 px-6 py-4 border-b border-red-500/20 bg-red-500/5">
          <div className="w-9 h-9 rounded-xl bg-red-500/20 border border-red-500/30 flex items-center justify-center flex-shrink-0">
            <Trash2 size={16} className="text-red-400" />
          </div>
          <div>
            <h2 className="text-base font-semibold text-white">Anonymize User PII</h2>
            <p className="text-xs text-red-300/70">This action cannot be undone</p>
          </div>
        </div>

        {/* Body */}
        <div className="px-6 py-5 space-y-4">
          <div className="p-3 rounded-lg bg-red-500/10 border border-red-500/20 space-y-2">
            <p className="text-sm font-semibold text-red-300">
              WARNING: Irreversible PII Anonymization
            </p>
            <ul className="text-xs text-red-200/70 space-y-1 list-disc list-inside">
              <li>
                All personally identifiable information for{' '}
                <strong className="text-red-200">{user.username}</strong> will be
                permanently anonymized.
              </li>
              <li>
                This action <strong>CANNOT be undone</strong>. The account cannot be
                restored.
              </li>
              <li>
                Audit log records referencing this user will be retained for{' '}
                <strong>7 years</strong> as required by law.
              </li>
              <li>
                The user will no longer be able to log in after anonymization.
              </li>
            </ul>
          </div>

          <label className="flex items-start gap-3 cursor-pointer">
            <input
              type="checkbox"
              checked={confirmed}
              onChange={(e) => setConfirmed(e.target.checked)}
              className="mt-0.5 accent-red-500 w-4 h-4 flex-shrink-0"
            />
            <span className="text-sm text-gray-300">
              I understand this action is irreversible and will permanently anonymize
              all PII for user{' '}
              <strong className="text-white">{user.username}</strong>.
            </span>
          </label>

          {apiError && (
            <div className="flex items-start gap-2 p-3 rounded-lg bg-red-500/10 border border-red-500/20">
              <AlertCircle size={14} className="text-red-400 mt-0.5 flex-shrink-0" />
              <p className="text-xs text-red-300">{apiError}</p>
            </div>
          )}

          <div className="flex items-center justify-end gap-2 pt-1">
            <button
              onClick={onClose}
              className="px-4 py-2 text-sm text-gray-400 hover:text-white transition-colors"
            >
              Cancel
            </button>
            <button
              onClick={() => mutation.mutate()}
              disabled={!confirmed || mutation.isPending}
              className="inline-flex items-center gap-2 px-5 py-2 bg-red-600 hover:bg-red-700 disabled:opacity-40 disabled:cursor-not-allowed text-white text-sm font-medium rounded-lg transition-colors"
            >
              {mutation.isPending && <Loader2 size={14} className="animate-spin" />}
              Anonymize PII
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}

// ── Toggle Active ──────────────────────────────────────────────────────────────

interface ToggleActiveBtnProps {
  user: AdminUser;
  onSuccess: () => void;
}

function ToggleActiveBtn({ user, onSuccess }: ToggleActiveBtnProps) {
  const mutation = useMutation({
    mutationFn: () =>
      updateUser(user.id, { ...user, isActive: !user.isActive } as EditUserPayload),
    onSuccess,
  });

  return (
    <button
      onClick={() => mutation.mutate()}
      disabled={mutation.isPending || !!user.deletedAt}
      title={user.isActive ? 'Deactivate user' : 'Activate user'}
      className={`inline-flex items-center gap-1 px-2 py-1 rounded-lg text-xs font-medium transition-colors disabled:opacity-40 disabled:cursor-not-allowed ${
        user.isActive
          ? 'bg-red-500/10 hover:bg-red-500/20 border border-red-500/25 text-red-400'
          : 'bg-green-500/10 hover:bg-green-500/20 border border-green-500/25 text-green-400'
      }`}
    >
      {mutation.isPending ? (
        <Loader2 size={11} className="animate-spin" />
      ) : user.isActive ? (
        <UserX size={11} />
      ) : (
        <CheckCircle2 size={11} />
      )}
      {user.isActive ? 'Deactivate' : 'Activate'}
    </button>
  );
}

// ── Main Page ──────────────────────────────────────────────────────────────────

export default function UserManagementPage() {
  const queryClient = useQueryClient();
  const [showCreateModal, setShowCreateModal] = useState(false);
  const [editUser, setEditUser] = useState<AdminUser | null>(null);
  const [deleteUser, setDeleteUser] = useState<AdminUser | null>(null);

  const { data: users, isLoading, isError, error, refetch, isFetching } = useQuery({
    queryKey: ['admin-users'],
    queryFn: fetchUsers,
  });

  function invalidate() {
    queryClient.invalidateQueries({ queryKey: ['admin-users'] });
  }

  return (
    <div>
      {/* Header */}
      <div className="flex items-start justify-between mb-6 gap-4 flex-wrap">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-xl bg-purple-500/10 border border-purple-500/20 flex items-center justify-center flex-shrink-0">
            <Users size={20} className="text-purple-400" />
          </div>
          <div>
            <h1 className="text-2xl font-bold text-white">User Management</h1>
            <p className="text-sm text-gray-400 mt-0.5">
              Manage accounts, roles, and access
            </p>
          </div>
        </div>
        <div className="flex items-center gap-2">
          <button
            onClick={() => refetch()}
            disabled={isFetching}
            className="inline-flex items-center gap-2 px-3 py-2 bg-surface-card border border-surface-border hover:border-accent/50 text-gray-400 hover:text-white text-sm rounded-lg transition-colors disabled:opacity-60"
          >
            <RefreshCw size={14} className={isFetching ? 'animate-spin' : ''} />
          </button>
          <button
            onClick={() => setShowCreateModal(true)}
            className="inline-flex items-center gap-2 px-4 py-2 bg-accent hover:bg-accent-hover text-white text-sm font-medium rounded-lg transition-colors"
          >
            <Plus size={15} />
            Create User
          </button>
        </div>
      </div>

      {/* Loading */}
      {isLoading && <TableSkeleton />}

      {/* Error */}
      {isError && (
        <div className="flex flex-col items-center gap-3 py-16 text-center">
          <AlertCircle size={40} className="text-red-400" />
          <p className="text-white font-semibold">Failed to load users</p>
          <p className="text-sm text-gray-400">
            {(error as Error)?.message ?? 'An unexpected error occurred.'}
          </p>
          <button
            onClick={() => refetch()}
            className="mt-2 px-4 py-2 bg-accent hover:bg-accent-hover text-white text-sm rounded-lg transition-colors"
          >
            Retry
          </button>
        </div>
      )}

      {/* Table */}
      {!isLoading && !isError && users && (
        <div
          className={`bg-surface-card border border-surface-border rounded-xl overflow-hidden transition-opacity ${
            isFetching ? 'opacity-70' : ''
          }`}
        >
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-surface-border">
                  {['ID', 'Name', 'Username', 'Email', 'Role', 'Phone', 'Status', 'Actions'].map(
                    (col) => (
                      <th
                        key={col}
                        className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap"
                      >
                        {col}
                      </th>
                    ),
                  )}
                </tr>
              </thead>
              <tbody>
                {users.length === 0 && (
                  <tr>
                    <td
                      colSpan={8}
                      className="px-4 py-12 text-center text-gray-500"
                    >
                      No users found.
                    </td>
                  </tr>
                )}
                {users.map((u, idx) => (
                  <tr
                    key={u.id}
                    className={`border-b border-surface-border/50 hover:bg-surface-hover transition-colors ${
                      idx % 2 === 0 ? '' : 'bg-surface/30'
                    } ${u.deletedAt ? 'opacity-50' : ''}`}
                  >
                    <td className="px-4 py-3 text-gray-500 font-mono text-xs">
                      #{u.id}
                    </td>
                    <td className="px-4 py-3 whitespace-nowrap">
                      <p className="text-white font-medium">
                        {u.firstName} {u.lastName}
                      </p>
                    </td>
                    <td className="px-4 py-3 text-gray-300 whitespace-nowrap">
                      {u.username}
                    </td>
                    <td className="px-4 py-3 text-gray-400 text-xs whitespace-nowrap">
                      {u.email}
                    </td>
                    <td className="px-4 py-3 whitespace-nowrap">
                      <span
                        className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border ${roleBadgeClass(
                          u.role,
                        )}`}
                      >
                        {roleLabel(u.role)}
                      </span>
                    </td>
                    <td className="px-4 py-3 text-gray-300 font-mono text-xs whitespace-nowrap">
                      {u.phone || '—'}
                    </td>
                    <td className="px-4 py-3 whitespace-nowrap">
                      {statusBadge(u)}
                    </td>
                    <td className="px-4 py-3 whitespace-nowrap">
                      <div className="flex items-center gap-1.5">
                        {/* Edit */}
                        <button
                          onClick={() => setEditUser(u)}
                          disabled={!!u.deletedAt}
                          title="Edit user"
                          className="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-xs font-medium bg-indigo-500/10 hover:bg-indigo-500/20 border border-indigo-500/25 text-indigo-400 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                        >
                          <Pencil size={11} />
                          Edit
                        </button>
                        {/* Toggle active */}
                        <ToggleActiveBtn user={u} onSuccess={invalidate} />
                        {/* Delete data */}
                        <button
                          onClick={() => setDeleteUser(u)}
                          disabled={!!u.deletedAt}
                          title="Anonymize PII"
                          className="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-xs font-medium bg-red-500/10 hover:bg-red-500/20 border border-red-500/25 text-red-400 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                        >
                          <Trash2 size={11} />
                          Delete Data
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {/* Footer count */}
          <div className="px-4 py-3 border-t border-surface-border">
            <p className="text-xs text-gray-500">
              {users.length} user{users.length !== 1 ? 's' : ''} total
            </p>
          </div>
        </div>
      )}

      {/* Modals */}
      {showCreateModal && (
        <UserFormModal
          mode="create"
          onClose={() => setShowCreateModal(false)}
          onSuccess={invalidate}
        />
      )}
      {editUser && (
        <UserFormModal
          mode="edit"
          user={editUser}
          onClose={() => setEditUser(null)}
          onSuccess={invalidate}
        />
      )}
      {deleteUser && (
        <DeleteDataModal
          user={deleteUser}
          onClose={() => setDeleteUser(null)}
          onSuccess={invalidate}
        />
      )}
    </div>
  );
}
