import { useState, useId } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { useMutation } from '@tanstack/react-query';
import { AlertCircle, CheckCircle2, Clock, Info, Send } from 'lucide-react';
import { createExceptionRequest } from '../../api/attendance';
import type { RequestType } from '../../types';

const REQUEST_TYPES: { value: RequestType; label: string }[] = [
  { value: 'CORRECTION', label: 'Time Correction' },
  { value: 'PTO', label: 'PTO (Paid Time Off)' },
  { value: 'LEAVE', label: 'Leave' },
  { value: 'BUSINESS_TRIP', label: 'Business Trip' },
  { value: 'OUTING', label: 'Outing' },
];

const POLICY_HINTS: Record<RequestType, string> = {
  CORRECTION:
    'Use this for correcting missed punches or incorrect clock-in/out times. Attach supporting documentation if available.',
  PTO:
    'PTO requests must be submitted and approved before the start date whenever possible. Balance deductions apply.',
  LEAVE:
    'Leave requests covering more than 3 consecutive days may require HR documentation.',
  BUSINESS_TRIP:
    'Business trips require manager pre-approval. Include travel dates and destination details in the reason field.',
  OUTING:
    'Team outings must be pre-authorized. Provide event name and organizer details in the reason field.',
};

function generateTimeOptions(): string[] {
  const options: string[] = [];
  for (let h = 0; h < 24; h++) {
    for (let m = 0; m < 60; m += 15) {
      const hh = String(h).padStart(2, '0');
      const mm = String(m).padStart(2, '0');
      options.push(`${hh}:${mm}`);
    }
  }
  return options;
}

const TIME_OPTIONS = generateTimeOptions();

function FieldLabel({ htmlFor, children }: { htmlFor: string; children: React.ReactNode }) {
  return (
    <label htmlFor={htmlFor} className="block text-sm font-medium text-gray-300 mb-1.5">
      {children}
    </label>
  );
}

const inputClass =
  'w-full bg-surface border border-surface-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-2 focus:ring-accent focus:border-transparent placeholder-gray-500 transition-colors';

const selectClass =
  'w-full bg-surface border border-surface-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-2 focus:ring-accent focus:border-transparent transition-colors appearance-none';

export default function ExceptionRequestForm() {
  const navigate = useNavigate();
  const formId = useId();

  // MM/DD/YYYY is the primary input contract (Prompt requirement).
  // We use explicit text inputs so the format is deterministic across
  // browsers and locales, then convert to ISO (YYYY-MM-DD) at submit time.
  const [requestType, setRequestType] = useState<RequestType>('CORRECTION');
  const [startDate, setStartDate] = useState(''); // MM/DD/YYYY
  const [endDate, setEndDate] = useState(''); // MM/DD/YYYY
  const [startTime, setStartTime] = useState('09:00');
  const [endTime, setEndTime] = useState('17:00');
  const [reason, setReason] = useState('');
  const [validationError, setValidationError] = useState<string | null>(null);

  const mutation = useMutation({
    mutationFn: createExceptionRequest,
    onSuccess: (data) => {
      navigate(`/attendance/request/${data.id}`);
    },
  });

  const mmddyyyyPattern = /^(0[1-9]|1[0-2])\/(0[1-9]|[12]\d|3[01])\/\d{4}$/;

  function mmddyyyyToIso(value: string): string | null {
    if (!mmddyyyyPattern.test(value)) return null;
    const [mm, dd, yyyy] = value.split('/');
    const d = new Date(`${yyyy}-${mm}-${dd}T00:00:00`);
    if (Number.isNaN(d.getTime())) return null;
    // Guard against silent rollover (e.g., 02/30/2026 -> 03/02)
    if (
      d.getFullYear() !== Number(yyyy) ||
      d.getMonth() + 1 !== Number(mm) ||
      d.getDate() !== Number(dd)
    ) {
      return null;
    }
    return `${yyyy}-${mm}-${dd}`;
  }

  function validate(): { error: string | null; startIso?: string; endIso?: string } {
    if (!startDate) return { error: 'Start date is required.' };
    if (!endDate) return { error: 'End date is required.' };
    const startIso = mmddyyyyToIso(startDate);
    if (startIso === null) return { error: 'Start date must be MM/DD/YYYY.' };
    const endIso = mmddyyyyToIso(endDate);
    if (endIso === null) return { error: 'End date must be MM/DD/YYYY.' };
    if (endIso < startIso) return { error: 'End date must be on or after start date.' };
    if (reason.trim().length < 10) return { error: 'Reason must be at least 10 characters.' };
    return { error: null, startIso, endIso };
  }

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    const result = validate();
    if (result.error || !result.startIso || !result.endIso) {
      setValidationError(result.error);
      return;
    }
    setValidationError(null);
    const clientKey = crypto.randomUUID();
    mutation.mutate({
      requestType,
      startDate: result.startIso,
      endDate: result.endIso,
      startTime,
      endTime,
      reason: reason.trim(),
      clientKey,
    });
  }

  if (mutation.isSuccess) {
    return (
      <div className="max-w-xl mx-auto">
        <div className="bg-surface-card border border-surface-border rounded-xl p-8 flex flex-col items-center gap-4 text-center">
          <CheckCircle2 size={48} className="text-green-400" />
          <h2 className="text-xl font-semibold text-white">Request Submitted</h2>
          <p className="text-gray-400 text-sm">
            Your exception request has been submitted and is pending approval.
          </p>
          <Link
            to={`/attendance/request/${mutation.data.id}`}
            className="mt-2 inline-flex items-center gap-2 px-5 py-2 bg-accent hover:bg-accent-hover text-white rounded-lg text-sm font-medium transition-colors"
          >
            View Request Detail
          </Link>
          <Link to="/attendance" className="text-sm text-gray-400 hover:text-gray-200 transition-colors">
            Back to Attendance
          </Link>
        </div>
      </div>
    );
  }

  return (
    <div className="max-w-2xl mx-auto">
      <h1 className="text-2xl font-bold text-white mb-6">Submit Exception Request</h1>

      <form onSubmit={handleSubmit} className="space-y-6">
        {/* Filing window note */}
        <div className="flex items-start gap-2 p-3 rounded-lg bg-blue-500/10 border border-blue-500/20">
          <Info size={16} className="text-blue-400 mt-0.5 flex-shrink-0" />
          <p className="text-sm text-blue-300">
            Requests must be filed within 7 calendar days of the exception date.
          </p>
        </div>

        {/* Request Type */}
        <div className="bg-surface-card border border-surface-border rounded-xl p-5 space-y-5">
          <div>
            <FieldLabel htmlFor={`${formId}-type`}>Request Type</FieldLabel>
            <div className="relative">
              <select
                id={`${formId}-type`}
                value={requestType}
                onChange={(e) => setRequestType(e.target.value as RequestType)}
                className={selectClass}
              >
                {REQUEST_TYPES.map((t) => (
                  <option key={t.value} value={t.value}>
                    {t.label}
                  </option>
                ))}
              </select>
            </div>
          </div>

          {/* Policy Hint */}
          <div className="flex items-start gap-2 p-3 rounded-lg bg-amber-500/10 border border-amber-500/20">
            <Info size={16} className="text-amber-400 mt-0.5 flex-shrink-0" />
            <p className="text-sm text-amber-200">{POLICY_HINTS[requestType]}</p>
          </div>
        </div>

        {/* Dates */}
        <div className="bg-surface-card border border-surface-border rounded-xl p-5">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <FieldLabel htmlFor={`${formId}-start-date`}>Start Date (MM/DD/YYYY)</FieldLabel>
              <input
                id={`${formId}-start-date`}
                type="text"
                inputMode="numeric"
                placeholder="MM/DD/YYYY"
                maxLength={10}
                pattern="(0[1-9]|1[0-2])/(0[1-9]|[12][0-9]|3[01])/\d{4}"
                value={startDate}
                onChange={(e) => setStartDate(e.target.value)}
                className={inputClass}
                aria-label="Start date in MM/DD/YYYY format"
                required
              />
            </div>
            <div>
              <FieldLabel htmlFor={`${formId}-end-date`}>End Date (MM/DD/YYYY)</FieldLabel>
              <input
                id={`${formId}-end-date`}
                type="text"
                inputMode="numeric"
                placeholder="MM/DD/YYYY"
                maxLength={10}
                pattern="(0[1-9]|1[0-2])/(0[1-9]|[12][0-9]|3[01])/\d{4}"
                value={endDate}
                onChange={(e) => setEndDate(e.target.value)}
                className={inputClass}
                aria-label="End date in MM/DD/YYYY format"
                required
              />
            </div>
          </div>
        </div>

        {/* Times */}
        <div className="bg-surface-card border border-surface-border rounded-xl p-5">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <FieldLabel htmlFor={`${formId}-start-time`}>Start Time</FieldLabel>
              <div className="relative">
                <Clock size={15} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none" />
                <select
                  id={`${formId}-start-time`}
                  value={startTime}
                  onChange={(e) => setStartTime(e.target.value)}
                  className={`${selectClass} pl-9`}
                >
                  {TIME_OPTIONS.map((t) => (
                    <option key={t} value={t}>
                      {t}
                    </option>
                  ))}
                </select>
              </div>
            </div>
            <div>
              <FieldLabel htmlFor={`${formId}-end-time`}>End Time</FieldLabel>
              <div className="relative">
                <Clock size={15} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none" />
                <select
                  id={`${formId}-end-time`}
                  value={endTime}
                  onChange={(e) => setEndTime(e.target.value)}
                  className={`${selectClass} pl-9`}
                >
                  {TIME_OPTIONS.map((t) => (
                    <option key={t} value={t}>
                      {t}
                    </option>
                  ))}
                </select>
              </div>
            </div>
          </div>
        </div>

        {/* Reason */}
        <div className="bg-surface-card border border-surface-border rounded-xl p-5">
          <FieldLabel htmlFor={`${formId}-reason`}>
            Reason{' '}
            <span className="text-gray-500 font-normal">(minimum 10 characters)</span>
          </FieldLabel>
          <textarea
            id={`${formId}-reason`}
            value={reason}
            onChange={(e) => setReason(e.target.value)}
            rows={4}
            placeholder="Describe the reason for this exception request..."
            className={`${inputClass} resize-none`}
            minLength={10}
            required
          />
          <p className={`mt-1 text-xs ${reason.trim().length >= 10 ? 'text-gray-500' : 'text-amber-500'}`}>
            {reason.trim().length} / 10 minimum characters
          </p>
        </div>

        {/* Validation error */}
        {validationError && (
          <div className="flex items-center gap-2 p-3 rounded-lg bg-red-500/10 border border-red-500/20">
            <AlertCircle size={16} className="text-red-400 flex-shrink-0" />
            <p className="text-sm text-red-300">{validationError}</p>
          </div>
        )}

        {/* API error */}
        {mutation.isError && (
          <div className="flex items-center gap-2 p-3 rounded-lg bg-red-500/10 border border-red-500/20">
            <AlertCircle size={16} className="text-red-400 flex-shrink-0" />
            <p className="text-sm text-red-300">
              {(mutation.error as Error)?.message ?? 'Failed to submit request. Please try again.'}
            </p>
          </div>
        )}

        {/* Submit */}
        <div className="flex items-center justify-end gap-3">
          <Link
            to="/attendance"
            className="px-4 py-2 text-sm text-gray-400 hover:text-white transition-colors"
          >
            Cancel
          </Link>
          <button
            type="submit"
            disabled={mutation.isPending}
            className="inline-flex items-center gap-2 px-5 py-2 bg-accent hover:bg-accent-hover disabled:opacity-60 disabled:cursor-not-allowed text-white rounded-lg text-sm font-medium transition-colors"
          >
            {mutation.isPending ? (
              <>
                <div className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
                Submitting...
              </>
            ) : (
              <>
                <Send size={15} />
                Submit Request
              </>
            )}
          </button>
        </div>
      </form>
    </div>
  );
}
