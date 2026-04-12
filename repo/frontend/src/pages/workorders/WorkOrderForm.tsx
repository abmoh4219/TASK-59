import { useState, useRef, useCallback, useId } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { useMutation } from '@tanstack/react-query';
import {
  ArrowLeft,
  Upload,
  X,
  AlertCircle,
  Loader2,
  ImageIcon,
  Send,
  Building2,
  DoorOpen,
  FileText,
  Tag,
  Zap,
} from 'lucide-react';
import { createWorkOrder } from '../../api/workOrders';
import type { WorkOrderPriority } from '../../types';

// ── Constants ────────────────────────────────────────────────────────────────

const CATEGORIES = ['Plumbing', 'Electrical', 'HVAC', 'General', 'Other'] as const;

const PRIORITIES: { value: WorkOrderPriority; label: string; color: string; dot: string }[] = [
  { value: 'LOW',    label: 'Low',    color: 'text-green-400',  dot: 'bg-green-400'  },
  { value: 'MEDIUM', label: 'Medium', color: 'text-amber-400',  dot: 'bg-amber-400'  },
  { value: 'HIGH',   label: 'High',   color: 'text-orange-400', dot: 'bg-orange-400' },
  { value: 'URGENT', label: 'Urgent', color: 'text-red-400',    dot: 'bg-red-400'    },
];

const MAX_PHOTOS = 5;
const ACCEPTED_MIME = ['image/jpeg', 'image/png'];
const MIN_DESC_LENGTH = 20;

// ── Helpers ───────────────────────────────────────────────────────────────────

const inputClass =
  'w-full bg-surface border border-surface-border rounded-lg px-3 py-2 text-white text-sm ' +
  'focus:outline-none focus:ring-2 focus:ring-accent focus:border-transparent ' +
  'placeholder-gray-500 transition-colors';

const selectClass =
  'w-full bg-surface border border-surface-border rounded-lg px-3 py-2 text-white text-sm ' +
  'focus:outline-none focus:ring-2 focus:ring-accent focus:border-transparent ' +
  'transition-colors appearance-none';

function FieldLabel({ htmlFor, children }: { htmlFor?: string; children: React.ReactNode }) {
  return (
    <label htmlFor={htmlFor} className="block text-sm font-medium text-gray-300 mb-1.5">
      {children}
    </label>
  );
}

function formatBytes(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

// ── Photo preview entry ───────────────────────────────────────────────────────

interface PhotoEntry {
  file: File;
  previewUrl: string;
}

// ── Main component ────────────────────────────────────────────────────────────

export default function WorkOrderForm() {
  const navigate = useNavigate();
  const formId = useId();
  const fileInputRef = useRef<HTMLInputElement>(null);

  const [category, setCategory] = useState<string>('General');
  const [priority, setPriority] = useState<WorkOrderPriority>('MEDIUM');
  const [description, setDescription] = useState('');
  const [building, setBuilding] = useState('');
  const [room, setRoom] = useState('');
  const [photos, setPhotos] = useState<PhotoEntry[]>([]);
  const [isDragging, setIsDragging] = useState(false);
  const [validationError, setValidationError] = useState<string | null>(null);

  const mutation = useMutation({
    mutationFn: createWorkOrder,
    onSuccess: (data) => {
      // Revoke object URLs to avoid memory leaks
      photos.forEach((p) => URL.revokeObjectURL(p.previewUrl));
      navigate(`/work-orders/${data.id}`);
    },
  });

  // ── Photo handling ─────────────────────────────────────────────────────────

  const addFiles = useCallback((files: FileList | File[]) => {
    const arr = Array.from(files);
    setPhotos((prev) => {
      const remaining = MAX_PHOTOS - prev.length;
      const accepted = arr
        .filter((f) => ACCEPTED_MIME.includes(f.type))
        .slice(0, remaining)
        .map((file) => ({ file, previewUrl: URL.createObjectURL(file) }));
      return [...prev, ...accepted];
    });
  }, []);

  const removePhoto = (index: number) => {
    setPhotos((prev) => {
      URL.revokeObjectURL(prev[index].previewUrl);
      return prev.filter((_, i) => i !== index);
    });
  };

  const handleDrop = (e: React.DragEvent) => {
    e.preventDefault();
    setIsDragging(false);
    addFiles(e.dataTransfer.files);
  };

  const handleDragOver = (e: React.DragEvent) => {
    e.preventDefault();
    setIsDragging(true);
  };

  const handleDragLeave = () => setIsDragging(false);

  const handleFileInput = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files) addFiles(e.target.files);
    // Reset so same file can be re-added if removed
    e.target.value = '';
  };

  // ── Validation & submit ────────────────────────────────────────────────────

  function validate(): string | null {
    if (!category) return 'Category is required.';
    if (!priority) return 'Priority is required.';
    if (description.trim().length < MIN_DESC_LENGTH)
      return `Description must be at least ${MIN_DESC_LENGTH} characters.`;
    if (!building.trim()) return 'Building is required.';
    if (!room.trim()) return 'Room is required.';
    return null;
  }

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    const err = validate();
    if (err) {
      setValidationError(err);
      return;
    }
    setValidationError(null);

    const formData = new FormData();
    formData.append('category', category);
    formData.append('priority', priority);
    formData.append('description', description.trim());
    formData.append('building', building.trim());
    formData.append('room', room.trim());
    photos.forEach((p) => formData.append('photos[]', p.file));

    mutation.mutate(formData);
  }

  // ── Derived state ──────────────────────────────────────────────────────────

  const descLen = description.trim().length;
  const descOk = descLen >= MIN_DESC_LENGTH;
  const atMaxPhotos = photos.length >= MAX_PHOTOS;
  const selectedPriority = PRIORITIES.find((p) => p.value === priority)!;

  return (
    <div className="max-w-2xl mx-auto">
      {/* Header */}
      <div className="mb-6">
        <Link
          to="/work-orders"
          className="inline-flex items-center gap-1.5 text-sm text-gray-400 hover:text-white transition-colors mb-4"
        >
          <ArrowLeft size={15} /> Back to Work Orders
        </Link>
        <h1 className="text-2xl font-bold text-white">Submit Work Order</h1>
        <p className="text-gray-400 text-sm mt-1">
          Describe the issue and we'll dispatch the right technician.
        </p>
      </div>

      <form onSubmit={handleSubmit} className="space-y-5">
        {/* Category + Priority */}
        <div className="bg-surface-card border border-surface-border rounded-xl p-5 space-y-4">
          <div className="flex items-center gap-2 mb-1">
            <Tag size={15} className="text-accent-light" />
            <span className="text-sm font-semibold text-white">Classification</span>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {/* Category */}
            <div>
              <FieldLabel htmlFor={`${formId}-category`}>Category</FieldLabel>
              <div className="relative">
                <select
                  id={`${formId}-category`}
                  value={category}
                  onChange={(e) => setCategory(e.target.value)}
                  className={selectClass}
                >
                  {CATEGORIES.map((c) => (
                    <option key={c} value={c}>{c}</option>
                  ))}
                </select>
                <div className="pointer-events-none absolute inset-y-0 right-3 flex items-center">
                  <svg className="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 20 20">
                    <path stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M6 8l4 4 4-4" />
                  </svg>
                </div>
              </div>
            </div>

            {/* Priority */}
            <div>
              <FieldLabel htmlFor={`${formId}-priority`}>Priority</FieldLabel>
              <div className="relative">
                <select
                  id={`${formId}-priority`}
                  value={priority}
                  onChange={(e) => setPriority(e.target.value as WorkOrderPriority)}
                  className={selectClass}
                >
                  {PRIORITIES.map((p) => (
                    <option key={p.value} value={p.value}>{p.label}</option>
                  ))}
                </select>
                <div className="pointer-events-none absolute inset-y-0 right-3 flex items-center">
                  <svg className="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 20 20">
                    <path stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M6 8l4 4 4-4" />
                  </svg>
                </div>
              </div>
              {/* Priority indicator pill */}
              <div className={`mt-1.5 flex items-center gap-1.5 text-xs ${selectedPriority.color}`}>
                <span className={`inline-block w-2 h-2 rounded-full ${selectedPriority.dot}`} />
                {selectedPriority.label} priority
              </div>
            </div>
          </div>
        </div>

        {/* Location */}
        <div className="bg-surface-card border border-surface-border rounded-xl p-5 space-y-4">
          <div className="flex items-center gap-2 mb-1">
            <Building2 size={15} className="text-accent-light" />
            <span className="text-sm font-semibold text-white">Location</span>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {/* Building */}
            <div>
              <FieldLabel htmlFor={`${formId}-building`}>Building</FieldLabel>
              <div className="relative">
                <Building2 size={15} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none" />
                <input
                  id={`${formId}-building`}
                  type="text"
                  value={building}
                  onChange={(e) => setBuilding(e.target.value)}
                  placeholder="e.g. Tower A"
                  className={`${inputClass} pl-9`}
                  required
                />
              </div>
            </div>

            {/* Room */}
            <div>
              <FieldLabel htmlFor={`${formId}-room`}>Room / Floor</FieldLabel>
              <div className="relative">
                <DoorOpen size={15} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none" />
                <input
                  id={`${formId}-room`}
                  type="text"
                  value={room}
                  onChange={(e) => setRoom(e.target.value)}
                  placeholder="e.g. 3F-302"
                  className={`${inputClass} pl-9`}
                  required
                />
              </div>
            </div>
          </div>
        </div>

        {/* Description */}
        <div className="bg-surface-card border border-surface-border rounded-xl p-5">
          <div className="flex items-center gap-2 mb-3">
            <FileText size={15} className="text-accent-light" />
            <span className="text-sm font-semibold text-white">Description</span>
          </div>
          <FieldLabel htmlFor={`${formId}-description`}>
            Describe the issue{' '}
            <span className="text-gray-500 font-normal">(min {MIN_DESC_LENGTH} chars)</span>
          </FieldLabel>
          <textarea
            id={`${formId}-description`}
            value={description}
            onChange={(e) => setDescription(e.target.value)}
            rows={4}
            placeholder="Provide a clear description of the issue, when it started, and any relevant observations..."
            className={`${inputClass} resize-none`}
          />
          <div className="flex items-center justify-between mt-1.5">
            <p className={`text-xs ${descOk ? 'text-gray-500' : 'text-amber-500'}`}>
              {descLen} / {MIN_DESC_LENGTH} minimum characters
            </p>
            {descOk && (
              <span className="text-xs text-green-400 flex items-center gap-1">
                <svg className="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                  <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                </svg>
                Good length
              </span>
            )}
          </div>
        </div>

        {/* Photo Upload */}
        <div className="bg-surface-card border border-surface-border rounded-xl p-5">
          <div className="flex items-center justify-between mb-3">
            <div className="flex items-center gap-2">
              <ImageIcon size={15} className="text-accent-light" />
              <span className="text-sm font-semibold text-white">Photos</span>
            </div>
            <span className={`text-xs px-2 py-0.5 rounded-full border ${
              atMaxPhotos
                ? 'bg-red-500/10 border-red-500/30 text-red-400'
                : 'bg-surface border-surface-border text-gray-400'
            }`}>
              {photos.length}/{MAX_PHOTOS} photos
            </span>
          </div>

          {/* Drop zone */}
          {!atMaxPhotos && (
            <div
              onDrop={handleDrop}
              onDragOver={handleDragOver}
              onDragLeave={handleDragLeave}
              onClick={() => fileInputRef.current?.click()}
              className={`
                relative border-2 border-dashed rounded-xl p-8 flex flex-col items-center justify-center gap-3
                cursor-pointer transition-all select-none
                ${isDragging
                  ? 'border-accent bg-accent/10 scale-[1.01]'
                  : 'border-surface-border hover:border-accent/50 hover:bg-surface-hover/40'
                }
              `}
            >
              <div className={`p-3 rounded-full ${isDragging ? 'bg-accent/20' : 'bg-surface-hover'}`}>
                <Upload size={20} className={isDragging ? 'text-accent-light' : 'text-gray-400'} />
              </div>
              <div className="text-center">
                <p className="text-sm text-gray-300 font-medium">
                  {isDragging ? 'Drop photos here' : 'Drag & drop or click to upload'}
                </p>
                <p className="text-xs text-gray-500 mt-0.5">JPEG, PNG — max 5 photos</p>
              </div>
              <input
                ref={fileInputRef}
                type="file"
                accept="image/jpeg,image/png"
                multiple
                className="hidden"
                onChange={handleFileInput}
              />
            </div>
          )}

          {atMaxPhotos && (
            <div className="flex items-center gap-2 p-3 rounded-lg bg-amber-500/10 border border-amber-500/20 mb-3">
              <Zap size={14} className="text-amber-400 flex-shrink-0" />
              <p className="text-xs text-amber-300">Maximum of {MAX_PHOTOS} photos reached. Remove one to add another.</p>
            </div>
          )}

          {/* Photo thumbnails */}
          {photos.length > 0 && (
            <div className={`grid grid-cols-2 sm:grid-cols-3 gap-3 ${!atMaxPhotos ? 'mt-4' : ''}`}>
              {photos.map((entry, i) => (
                <div
                  key={entry.previewUrl}
                  className="group relative bg-surface rounded-lg overflow-hidden border border-surface-border aspect-square"
                >
                  <img
                    src={entry.previewUrl}
                    alt={entry.file.name}
                    className="w-full h-full object-cover"
                  />
                  {/* Overlay on hover */}
                  <div className="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity flex flex-col items-center justify-center gap-1 p-2">
                    <p className="text-white text-xs font-medium text-center line-clamp-2 leading-tight">
                      {entry.file.name}
                    </p>
                    <p className="text-gray-300 text-xs">{formatBytes(entry.file.size)}</p>
                  </div>
                  {/* Remove button */}
                  <button
                    type="button"
                    onClick={() => removePhoto(i)}
                    className="absolute top-1.5 right-1.5 w-6 h-6 bg-red-500 hover:bg-red-600 rounded-full flex items-center justify-center shadow-lg transition-colors"
                    title="Remove photo"
                  >
                    <X size={12} className="text-white" />
                  </button>
                  {/* Index badge */}
                  <span className="absolute bottom-1.5 left-1.5 w-5 h-5 bg-black/70 rounded-full flex items-center justify-center">
                    <span className="text-white text-[10px] font-bold">{i + 1}</span>
                  </span>
                </div>
              ))}
            </div>
          )}
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
              {(mutation.error as Error)?.message ?? 'Failed to submit work order. Please try again.'}
            </p>
          </div>
        )}

        {/* Actions */}
        <div className="flex items-center justify-end gap-3 pb-6">
          <Link
            to="/work-orders"
            className="px-4 py-2 text-sm text-gray-400 hover:text-white transition-colors"
          >
            Cancel
          </Link>
          <button
            type="submit"
            disabled={mutation.isPending}
            className="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-accent to-accent-dark hover:from-accent-hover hover:to-accent disabled:opacity-60 disabled:cursor-not-allowed text-white rounded-lg text-sm font-medium transition-all shadow-glow"
          >
            {mutation.isPending ? (
              <>
                <Loader2 size={15} className="animate-spin" />
                Submitting...
              </>
            ) : (
              <>
                <Send size={15} />
                Submit Work Order
              </>
            )}
          </button>
        </div>
      </form>
    </div>
  );
}
