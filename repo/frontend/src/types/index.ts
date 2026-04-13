export type UserRole =
  | 'ROLE_ADMIN'
  | 'ROLE_HR_ADMIN'
  | 'ROLE_SUPERVISOR'
  | 'ROLE_DISPATCHER'
  | 'ROLE_TECHNICIAN'
  | 'ROLE_EMPLOYEE';

export interface User {
  id: number;
  username: string;
  email: string;
  firstName: string;
  lastName: string;
  role: UserRole;
  phone?: string;
  isActive: boolean;
  isOut: boolean;
}

export interface AuthState {
  user: User | null;
  csrfToken: string | null;
  isAuthenticated: boolean;
  isLoading: boolean;
}

export type ExceptionType =
  | 'LATE_ARRIVAL'
  | 'EARLY_LEAVE'
  | 'MISSED_PUNCH'
  | 'ABSENCE'
  | 'APPROVED_OFFSITE';

export type RequestType =
  | 'CORRECTION'
  | 'PTO'
  | 'LEAVE'
  | 'BUSINESS_TRIP'
  | 'OUTING';

export type RequestStatus =
  | 'PENDING'
  | 'APPROVED'
  | 'REJECTED'
  | 'WITHDRAWN';

export type ApprovalAction =
  | 'APPROVE'
  | 'REJECT'
  | 'ESCALATE'
  | 'REASSIGN'
  | 'WITHDRAW';

export type WorkOrderStatus =
  | 'submitted'
  | 'dispatched'
  | 'accepted'
  | 'in_progress'
  | 'completed'
  | 'rated';

export type WorkOrderPriority = 'LOW' | 'MEDIUM' | 'HIGH' | 'URGENT';

export interface PunchEvent {
  id: number;
  eventTime: string;
  eventType: 'IN' | 'OUT';
}

export interface AttendanceRecord {
  id: number;
  recordDate: string;
  firstPunchIn: string | null;
  lastPunchOut: string | null;
  totalMinutes: number;
  exceptions: ExceptionType[];
  punches: PunchEvent[];
  shiftStart: string;
  shiftEnd: string;
}

export interface ExceptionRequest {
  id: number;
  requestType: RequestType;
  startDate: string;
  endDate: string;
  startTime: string;
  endTime: string;
  reason: string;
  status: RequestStatus;
  filedAt: string;
  steps: ApprovalStep[];
}

export interface ApprovalStep {
  id: number;
  stepNumber: number;
  approverName: string;
  approverRole: string;
  approverIsOut?: boolean;
  status: string;
  slaDeadline: string;
  remainingMinutes: number;
  actedAt: string | null;
}

export interface WorkOrder {
  id: number;
  category: string;
  priority: WorkOrderPriority;
  description: string;
  building: string;
  room: string;
  status: WorkOrderStatus;
  submittedByName: string;
  submittedById: number;
  assignedTechnicianName: string | null;
  assignedTechnicianId: number | null;
  assignedDispatcherName: string | null;
  rating: number | null;
  completionNotes: string | null;
  createdAt: string;
  dispatchedAt: string | null;
  acceptedAt: string | null;
  startedAt: string | null;
  completedAt: string | null;
  ratedAt: string | null;
  photos: WorkOrderPhoto[];
}

export interface WorkOrderPhoto {
  id: number;
  originalFilename: string;
  url: string;
}

export interface Resource {
  id: number;
  name: string;
  type: string;
  costCenter: string;
  capacity: number;
  isAvailable: boolean;
  description: string;
}

export interface Booking {
  id: number;
  resourceName: string;
  startDatetime: string;
  endDatetime: string;
  purpose: string;
  status: string;
  allocations: BookingAllocation[];
}

export interface BookingAllocation {
  travelerId: number;
  travelerName: string;
  costCenter: string;
  amount: number;
}

export interface AuditLogEntry {
  id: number;
  actorUsername: string;
  action: string;
  entityType: string;
  entityId: number;
  ipAddress: string;
  createdAt: string;
  oldValue: Record<string, unknown>;
  newValue: Record<string, unknown>;
}
