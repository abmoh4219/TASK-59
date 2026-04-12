import { useState, useRef, useCallback } from 'react';
import { useMutation } from '@tanstack/react-query';
import {
  Upload,
  FileText,
  X,
  CheckCircle2,
  AlertCircle,
  Info,
  Terminal,
  AlertTriangle,
  Loader2,
} from 'lucide-react';
import apiClient from '../../api/client';

// ── Types ──────────────────────────────────────────────────────────────────────

interface ImportResult {
  imported: number;
  skipped: number;
  errors: string[];
}

// ── API ────────────────────────────────────────────────────────────────────────

async function importCsv(file: File): Promise<ImportResult> {
  const formData = new FormData();
  formData.append('file', file);
  const res = await apiClient.post<ImportResult>(
    '/admin/attendance/import',
    formData,
    {
      headers: { 'Content-Type': 'multipart/form-data' },
    },
  );
  return res.data;
}

// ── File Drop Zone ─────────────────────────────────────────────────────────────

interface DropZoneProps {
  file: File | null;
  onFile: (f: File) => void;
  onClear: () => void;
}

function DropZone({ file, onFile, onClear }: DropZoneProps) {
  const inputRef = useRef<HTMLInputElement>(null);
  const [dragging, setDragging] = useState(false);

  const handleDrop = useCallback(
    (e: React.DragEvent<HTMLDivElement>) => {
      e.preventDefault();
      setDragging(false);
      const dropped = e.dataTransfer.files[0];
      if (dropped && dropped.name.endsWith('.csv')) {
        onFile(dropped);
      }
    },
    [onFile],
  );

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const selected = e.target.files?.[0];
    if (selected) onFile(selected);
  };

  if (file) {
    return (
      <div className="flex items-center gap-4 p-5 bg-green-500/5 border border-green-500/20 rounded-xl">
        <div className="w-10 h-10 rounded-xl bg-green-500/15 border border-green-500/25 flex items-center justify-center flex-shrink-0">
          <FileText size={20} className="text-green-400" />
        </div>
        <div className="flex-1 min-w-0">
          <p className="text-sm font-medium text-white truncate">{file.name}</p>
          <p className="text-xs text-gray-400 mt-0.5">
            {(file.size / 1024).toFixed(1)} KB · CSV file ready to import
          </p>
        </div>
        <button
          onClick={onClear}
          className="p-2 text-gray-500 hover:text-red-400 transition-colors rounded-lg hover:bg-red-500/10"
          title="Remove file"
        >
          <X size={16} />
        </button>
      </div>
    );
  }

  return (
    <div
      onDragOver={(e) => {
        e.preventDefault();
        setDragging(true);
      }}
      onDragLeave={() => setDragging(false)}
      onDrop={handleDrop}
      onClick={() => inputRef.current?.click()}
      className={`flex flex-col items-center justify-center gap-3 p-10 rounded-xl border-2 border-dashed cursor-pointer transition-colors ${
        dragging
          ? 'border-accent bg-accent/5'
          : 'border-surface-border hover:border-accent/50 hover:bg-surface-hover'
      }`}
    >
      <div className="w-14 h-14 rounded-2xl bg-accent/10 border border-accent/20 flex items-center justify-center">
        <Upload size={26} className="text-accent" />
      </div>
      <div className="text-center">
        <p className="text-white font-medium">
          {dragging ? 'Drop your CSV file here' : 'Drag & drop your CSV file'}
        </p>
        <p className="text-sm text-gray-500 mt-1">
          or{' '}
          <span className="text-accent hover:text-accent-light font-medium transition-colors">
            click to browse
          </span>
        </p>
      </div>
      <p className="text-xs text-gray-600">.csv files only · max 10 MB</p>
      <input
        ref={inputRef}
        type="file"
        accept=".csv"
        className="hidden"
        onChange={handleFileChange}
      />
    </div>
  );
}

// ── Import Result Panel ────────────────────────────────────────────────────────

function ResultPanel({ result }: { result: ImportResult }) {
  return (
    <div className="space-y-4">
      {/* Summary */}
      <div className="grid grid-cols-3 gap-3">
        <div className="p-4 rounded-xl bg-green-500/10 border border-green-500/20 text-center">
          <p className="text-2xl font-bold text-green-400">{result.imported}</p>
          <p className="text-xs text-gray-400 mt-1">Records Imported</p>
        </div>
        <div className="p-4 rounded-xl bg-amber-500/10 border border-amber-500/20 text-center">
          <p className="text-2xl font-bold text-amber-400">{result.skipped}</p>
          <p className="text-xs text-gray-400 mt-1">Rows Skipped</p>
        </div>
        <div
          className={`p-4 rounded-xl text-center border ${
            result.errors.length > 0
              ? 'bg-red-500/10 border-red-500/20'
              : 'bg-surface-hover border-surface-border'
          }`}
        >
          <p
            className={`text-2xl font-bold ${
              result.errors.length > 0 ? 'text-red-400' : 'text-gray-400'
            }`}
          >
            {result.errors.length}
          </p>
          <p className="text-xs text-gray-400 mt-1">Errors</p>
        </div>
      </div>

      {/* Error list */}
      {result.errors.length > 0 && (
        <div className="p-4 rounded-xl bg-red-500/5 border border-red-500/20">
          <div className="flex items-center gap-2 mb-3">
            <AlertTriangle size={15} className="text-red-400" />
            <p className="text-sm font-semibold text-red-300">Import Errors</p>
          </div>
          <ul className="space-y-1.5 max-h-48 overflow-y-auto pr-1">
            {result.errors.map((err, i) => (
              <li key={i} className="flex items-start gap-2 text-xs text-red-200/70">
                <span className="text-red-500 font-mono flex-shrink-0">#{i + 1}</span>
                {err}
              </li>
            ))}
          </ul>
        </div>
      )}

      {result.imported > 0 && result.errors.length === 0 && (
        <div className="flex items-center gap-2 p-3 rounded-lg bg-green-500/10 border border-green-500/20">
          <CheckCircle2 size={16} className="text-green-400 flex-shrink-0" />
          <p className="text-sm text-green-300">
            Import completed successfully with no errors.
          </p>
        </div>
      )}
    </div>
  );
}

// ── Main Page ──────────────────────────────────────────────────────────────────

export default function CsvImportPage() {
  const [file, setFile] = useState<File | null>(null);
  const [lastResult, setLastResult] = useState<ImportResult | null>(null);

  const mutation = useMutation({
    mutationFn: () => importCsv(file!),
    onSuccess: (data) => {
      setLastResult(data);
      setFile(null);
    },
  });

  function handleClear() {
    setFile(null);
    mutation.reset();
    setLastResult(null);
  }

  return (
    <div className="max-w-3xl mx-auto">
      {/* Header */}
      <div className="flex items-center gap-3 mb-6">
        <div className="w-10 h-10 rounded-xl bg-blue-500/10 border border-blue-500/20 flex items-center justify-center flex-shrink-0">
          <Upload size={20} className="text-blue-400" />
        </div>
        <div>
          <h1 className="text-2xl font-bold text-white">CSV Attendance Import</h1>
          <p className="text-sm text-gray-400 mt-0.5">
            Bulk import punch events from CSV files
          </p>
        </div>
      </div>

      {/* Format guide */}
      <div className="bg-surface-card border border-surface-border rounded-xl p-5 mb-6 space-y-4">
        <div className="flex items-center gap-2">
          <Info size={16} className="text-accent" />
          <h2 className="text-sm font-semibold text-white">CSV Format Requirements</h2>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-xs">
            <thead>
              <tr className="border-b border-surface-border">
                {['Column', 'Required', 'Format', 'Example'].map((h) => (
                  <th
                    key={h}
                    className="px-3 py-2 text-left text-gray-500 uppercase tracking-wide font-semibold"
                  >
                    {h}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-surface-border/50">
              {[
                ['employee_id', 'Yes', 'Integer', '42'],
                ['date', 'Yes', 'MM/DD/YYYY', '04/15/2024'],
                ['event_type', 'Yes', 'IN or OUT', 'IN'],
                ['time', 'Yes', 'HH:MM:SS (24h)', '09:05:12'],
              ].map(([col, req, fmt, ex]) => (
                <tr key={col} className="hover:bg-surface-hover transition-colors">
                  <td className="px-3 py-2 font-mono text-accent">{col}</td>
                  <td className="px-3 py-2">
                    <span
                      className={`inline-flex px-1.5 py-0.5 rounded text-xs font-medium ${
                        req === 'Yes'
                          ? 'bg-red-500/15 text-red-300'
                          : 'bg-gray-500/15 text-gray-400'
                      }`}
                    >
                      {req}
                    </span>
                  </td>
                  <td className="px-3 py-2 text-gray-400">{fmt}</td>
                  <td className="px-3 py-2 font-mono text-gray-300">{ex}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        <div className="flex items-start gap-2 p-3 rounded-lg bg-blue-500/10 border border-blue-500/15">
          <Info size={14} className="text-blue-400 flex-shrink-0 mt-0.5" />
          <p className="text-xs text-blue-300">
            The first row must be the header row with exact column names as shown above.
            Duplicate records (same employee_id + date + event_type + time) will be skipped.
          </p>
        </div>
      </div>

      {/* Drop zone */}
      {!lastResult && (
        <div className="bg-surface-card border border-surface-border rounded-xl p-5 mb-5">
          <h2 className="text-sm font-semibold text-white mb-4">Upload File</h2>
          <DropZone
            file={file}
            onFile={(f) => {
              setFile(f);
              mutation.reset();
            }}
            onClear={handleClear}
          />
        </div>
      )}

      {/* Upload button + API error */}
      {file && !lastResult && (
        <div className="space-y-3">
          {mutation.isError && (
            <div className="flex items-start gap-2 p-3 rounded-lg bg-red-500/10 border border-red-500/20">
              <AlertCircle size={15} className="text-red-400 flex-shrink-0 mt-0.5" />
              <p className="text-sm text-red-300">
                {(mutation.error as { response?: { data?: { message?: string } } })
                  ?.response?.data?.message ??
                  (mutation.error as Error)?.message ??
                  'Import failed. Please check your file and try again.'}
              </p>
            </div>
          )}
          <button
            onClick={() => mutation.mutate()}
            disabled={mutation.isPending}
            className="w-full inline-flex items-center justify-center gap-2 px-5 py-3 bg-accent hover:bg-accent-hover disabled:opacity-60 disabled:cursor-not-allowed text-white font-medium rounded-xl transition-colors"
          >
            {mutation.isPending ? (
              <>
                <Loader2 size={16} className="animate-spin" />
                Importing...
              </>
            ) : (
              <>
                <Upload size={16} />
                Import CSV
              </>
            )}
          </button>
        </div>
      )}

      {/* Result */}
      {lastResult && (
        <div className="bg-surface-card border border-surface-border rounded-xl p-5 mb-5 space-y-5">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-2">
              <CheckCircle2 size={18} className="text-green-400" />
              <h2 className="text-sm font-semibold text-white">Import Result</h2>
            </div>
            <button
              onClick={handleClear}
              className="px-3 py-1.5 text-xs text-gray-400 hover:text-white border border-surface-border hover:border-accent/40 rounded-lg transition-colors"
            >
              Import Another
            </button>
          </div>
          <ResultPanel result={lastResult} />
        </div>
      )}

      {/* CLI Alternative */}
      <div className="bg-surface-card border border-surface-border rounded-xl p-5">
        <div className="flex items-center gap-2 mb-3">
          <Terminal size={16} className="text-gray-400" />
          <h2 className="text-sm font-semibold text-white">CLI Alternative</h2>
        </div>
        <p className="text-xs text-gray-500 mb-3">
          For large files or automated pipelines, use the console command directly
          inside the backend container:
        </p>
        <div className="bg-surface rounded-lg border border-surface-border p-3 font-mono text-xs text-green-300 overflow-x-auto">
          <p className="text-gray-500 select-none"># Run inside the backend container</p>
          <p>
            docker compose exec backend php bin/console app:import-attendance
            --file=/path/to/attendance.csv
          </p>
          <p className="mt-2 text-gray-500 select-none"># Or via docker cp first:</p>
          <p>docker cp attendance.csv &lt;container&gt;:/tmp/attendance.csv</p>
          <p>
            docker compose exec backend php bin/console app:import-attendance
            --file=/tmp/attendance.csv
          </p>
        </div>
        <div className="flex items-start gap-2 mt-3 p-3 rounded-lg bg-amber-500/10 border border-amber-500/15">
          <AlertTriangle size={13} className="text-amber-400 flex-shrink-0 mt-0.5" />
          <p className="text-xs text-amber-300">
            The CLI command streams output line-by-line and is preferred for files
            larger than 50 MB. The web UI upload is limited to 10 MB.
          </p>
        </div>
      </div>
    </div>
  );
}
