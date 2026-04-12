import React from 'react';
import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';
import Skeleton from './Skeleton';
import EmptyState from './EmptyState';
import { TableIcon } from 'lucide-react';

// ─── Types ────────────────────────────────────────────────────────────────────

export interface Column<T> {
  key: string;
  label: string;
  /** Optional custom renderer. Receives the row item and its index. */
  render?: (item: T, index: number) => React.ReactNode;
}

interface TableProps<T> {
  columns: Column<T>[];
  data: T[];
  loading?: boolean;
  emptyMessage?: string;
  /** Called when a body row is clicked. Enables hover styling on rows. */
  onRowClick?: (item: T, index: number) => void;
  className?: string;
  /** Key extractor for row identity. Defaults to index. */
  rowKey?: (item: T, index: number) => string | number;
}

// ─── Skeleton rows (5 shimmer rows) ──────────────────────────────────────────

function SkeletonRows({ colCount }: { colCount: number }) {
  return (
    <>
      {Array.from({ length: 5 }).map((_, rowIdx) => (
        <tr key={rowIdx} className="border-b border-surface-border/50">
          {Array.from({ length: colCount }).map((_, colIdx) => (
            <td key={colIdx} className="px-4 py-3">
              <Skeleton height={16} width={colIdx === 0 ? 120 : 80} rounded="md" />
            </td>
          ))}
        </tr>
      ))}
    </>
  );
}

// ─── Generic Table ────────────────────────────────────────────────────────────

function Table<T extends object>({
  columns,
  data,
  loading = false,
  emptyMessage = 'No data to display.',
  onRowClick,
  className,
  rowKey,
}: TableProps<T>) {
  const isEmpty = !loading && data.length === 0;

  return (
    <div
      className={twMerge(
        clsx('w-full overflow-x-auto rounded-xl border border-surface-border', className)
      )}
    >
      <table className="w-full text-sm text-left border-collapse">
        {/* Sticky header */}
        <thead className="sticky top-0 z-10 bg-surface-hover">
          <tr>
            {columns.map((col) => (
              <th
                key={col.key}
                scope="col"
                className="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500 border-b border-surface-border"
              >
                {col.label}
              </th>
            ))}
          </tr>
        </thead>

        <tbody>
          {loading ? (
            <SkeletonRows colCount={columns.length} />
          ) : isEmpty ? (
            <tr>
              <td colSpan={columns.length}>
                <EmptyState
                  icon={<TableIcon size={28} />}
                  title="No results"
                  description={emptyMessage}
                />
              </td>
            </tr>
          ) : (
            data.map((item, rowIdx) => {
              const key = rowKey ? rowKey(item, rowIdx) : rowIdx;
              const isClickable = typeof onRowClick === 'function';

              return (
                <tr
                  key={key}
                  onClick={isClickable ? () => onRowClick(item, rowIdx) : undefined}
                  className={clsx(
                    // Alternating rows
                    rowIdx % 2 === 0 ? 'bg-surface-card' : 'bg-surface/50',
                    // Border between rows
                    'border-b border-surface-border/50 last:border-b-0',
                    // Hover styles
                    isClickable && 'cursor-pointer hover:bg-surface-hover/50 transition-colors duration-100'
                  )}
                >
                  {columns.map((col) => {
                    const rawValue = (item as Record<string, unknown>)[col.key];
                    const cellContent = col.render
                      ? col.render(item, rowIdx)
                      : (rawValue as React.ReactNode) ?? '—';

                    return (
                      <td key={col.key} className="px-4 py-3 text-gray-300 align-middle">
                        {cellContent}
                      </td>
                    );
                  })}
                </tr>
              );
            })
          )}
        </tbody>
      </table>
    </div>
  );
}

export default Table;
