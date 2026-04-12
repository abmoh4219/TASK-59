import React from 'react';
import { Check, X, Clock, Circle } from 'lucide-react';
import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

// ─── Types ────────────────────────────────────────────────────────────────────

export type TimelineItemStatus = 'completed' | 'current' | 'pending' | 'failed';

export interface TimelineItem {
  id: string | number;
  label: string;
  description?: string;
  status: TimelineItemStatus;
  /** Optional ISO string or formatted timestamp shown below description */
  timestamp?: string;
}

interface TimelineProps {
  items: TimelineItem[];
  className?: string;
}

// ─── Status node config ───────────────────────────────────────────────────────

interface NodeConfig {
  icon: React.ReactNode;
  /** Outer ring colour (Tailwind class string) */
  ringClass: string;
  /** Fill/background for the circle node */
  bgClass: string;
  /** Icon colour */
  iconClass: string;
  /** Optional glow shadow via inline style */
  glow?: string;
}

function getNodeConfig(status: TimelineItemStatus, size: number = 16): NodeConfig {
  switch (status) {
    case 'completed':
      return {
        icon: <Check size={size} strokeWidth={2.5} />,
        ringClass: 'ring-2 ring-accent/40',
        bgClass: 'bg-accent',
        iconClass: 'text-white',
        glow: '0 0 10px rgba(99,102,241,0.4)',
      };
    case 'current':
      return {
        icon: <Circle size={size} strokeWidth={2.5} />,
        ringClass: 'ring-2 ring-accent animate-pulse',
        bgClass: 'bg-accent/20',
        iconClass: 'text-accent-light',
        glow: '0 0 14px rgba(99,102,241,0.6)',
      };
    case 'failed':
      return {
        icon: <X size={size} strokeWidth={2.5} />,
        ringClass: 'ring-2 ring-red-500/40',
        bgClass: 'bg-red-500/20',
        iconClass: 'text-red-400',
      };
    case 'pending':
    default:
      return {
        icon: <Clock size={size} strokeWidth={2} />,
        ringClass: 'ring-1 ring-surface-border',
        bgClass: 'bg-surface-hover',
        iconClass: 'text-gray-500',
      };
  }
}

// ─── Label colours ────────────────────────────────────────────────────────────

function getLabelClass(status: TimelineItemStatus): string {
  switch (status) {
    case 'completed': return 'text-white';
    case 'current':   return 'text-accent-light font-semibold';
    case 'failed':    return 'text-red-400';
    case 'pending':   return 'text-gray-500';
  }
}

// ─── Connector line colour ────────────────────────────────────────────────────

function getConnectorClass(status: TimelineItemStatus): string {
  switch (status) {
    case 'completed': return 'bg-accent/50';
    case 'current':   return 'bg-accent/30';
    default:          return 'bg-surface-border';
  }
}

// ─── Timeline Component ───────────────────────────────────────────────────────

const Timeline: React.FC<TimelineProps> = ({ items, className }) => (
  <ol
    className={twMerge(clsx('relative flex flex-col gap-0', className))}
    aria-label="Progress timeline"
  >
    {items.map((item, idx) => {
      const isLast = idx === items.length - 1;
      const node = getNodeConfig(item.status);

      return (
        <li key={item.id} className="flex gap-4">
          {/* Left column: node + connector */}
          <div className="flex flex-col items-center">
            {/* Status circle node */}
            <div
              className={clsx(
                'w-8 h-8 rounded-full flex items-center justify-center shrink-0 z-10',
                node.bgClass,
                node.ringClass,
                node.iconClass
              )}
              style={node.glow ? { boxShadow: node.glow } : undefined}
              aria-label={`Step ${idx + 1}: ${item.status}`}
            >
              {node.icon}
            </div>

            {/* Vertical connector line (hidden for last item) */}
            {!isLast && (
              <div
                className={clsx('w-px flex-1 mt-1 mb-1 min-h-[24px]', getConnectorClass(item.status))}
              />
            )}
          </div>

          {/* Right column: label + description + timestamp */}
          <div className={clsx('pb-6', isLast && 'pb-0')}>
            <p className={clsx('text-sm leading-none mt-2', getLabelClass(item.status))}>
              {item.label}
            </p>
            {item.description && (
              <p className="mt-1.5 text-xs text-gray-500 leading-relaxed">{item.description}</p>
            )}
            {item.timestamp && (
              <time className="mt-1 block text-xs text-gray-600">{item.timestamp}</time>
            )}
          </div>
        </li>
      );
    })}
  </ol>
);

export default Timeline;
