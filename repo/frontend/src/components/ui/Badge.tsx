import React from 'react';
import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export type BadgeVariant =
  // Role badges
  | 'role-employee'
  | 'role-supervisor'
  | 'role-hr-admin'
  | 'role-dispatcher'
  | 'role-technician'
  | 'role-admin'
  // Exception badges
  | 'exception-late'
  | 'exception-missed'
  | 'exception-absent'
  | 'exception-early'
  | 'exception-offsite'
  // Work order status badges
  | 'wo-submitted'
  | 'wo-dispatched'
  | 'wo-accepted'
  | 'wo-in-progress'
  | 'wo-completed'
  | 'wo-rated'
  // Fallback
  | 'default';

interface BadgeProps {
  variant?: BadgeVariant;
  children?: React.ReactNode;
  className?: string;
}

/**
 * Soft-colored pill badge.
 * Each variant uses bg-{color}/10 + text-{color}-400 + border-{color}/30.
 * Uses inline styles for colors to bypass Tailwind's JIT purging of dynamic class names.
 */
const variantStyles: Record<BadgeVariant, React.CSSProperties> = {
  // Role — using badge colors from tailwind.config
  'role-employee':    { backgroundColor: 'rgba(59,130,246,0.1)',  color: '#60A5FA', borderColor: 'rgba(59,130,246,0.3)'  }, // blue
  'role-supervisor':  { backgroundColor: 'rgba(139,92,246,0.1)', color: '#A78BFA', borderColor: 'rgba(139,92,246,0.3)' }, // purple
  'role-hr-admin':    { backgroundColor: 'rgba(239,68,68,0.1)',  color: '#F87171', borderColor: 'rgba(239,68,68,0.3)'  }, // red
  'role-dispatcher':  { backgroundColor: 'rgba(249,115,22,0.1)', color: '#FB923C', borderColor: 'rgba(249,115,22,0.3)' }, // orange
  'role-technician':  { backgroundColor: 'rgba(34,197,94,0.1)',  color: '#4ADE80', borderColor: 'rgba(34,197,94,0.3)'  }, // green
  'role-admin':       { backgroundColor: 'rgba(234,179,8,0.1)',  color: '#FACC15', borderColor: 'rgba(234,179,8,0.3)'  }, // gold

  // Exceptions
  'exception-late':    { backgroundColor: 'rgba(245,158,11,0.1)', color: '#FCD34D', borderColor: 'rgba(245,158,11,0.3)' }, // amber
  'exception-missed':  { backgroundColor: 'rgba(239,68,68,0.1)',  color: '#F87171', borderColor: 'rgba(239,68,68,0.3)'  }, // red
  'exception-absent':  { backgroundColor: 'rgba(239,68,68,0.1)',  color: '#F87171', borderColor: 'rgba(239,68,68,0.3)'  }, // red
  'exception-early':   { backgroundColor: 'rgba(249,115,22,0.1)', color: '#FB923C', borderColor: 'rgba(249,115,22,0.3)' }, // orange
  'exception-offsite': { backgroundColor: 'rgba(34,197,94,0.1)',  color: '#4ADE80', borderColor: 'rgba(34,197,94,0.3)'  }, // green

  // Work order statuses
  'wo-submitted':   { backgroundColor: 'rgba(107,114,128,0.1)', color: '#9CA3AF', borderColor: 'rgba(107,114,128,0.3)' }, // gray
  'wo-dispatched':  { backgroundColor: 'rgba(59,130,246,0.1)',  color: '#60A5FA', borderColor: 'rgba(59,130,246,0.3)'  }, // blue
  'wo-accepted':    { backgroundColor: 'rgba(99,102,241,0.1)',  color: '#818CF8', borderColor: 'rgba(99,102,241,0.3)'  }, // indigo
  'wo-in-progress': { backgroundColor: 'rgba(245,158,11,0.1)',  color: '#FCD34D', borderColor: 'rgba(245,158,11,0.3)'  }, // amber
  'wo-completed':   { backgroundColor: 'rgba(34,197,94,0.1)',   color: '#4ADE80', borderColor: 'rgba(34,197,94,0.3)'   }, // green
  'wo-rated':       { backgroundColor: 'rgba(20,184,166,0.1)',  color: '#2DD4BF', borderColor: 'rgba(20,184,166,0.3)'  }, // teal

  // Default
  'default': { backgroundColor: 'rgba(107,114,128,0.1)', color: '#9CA3AF', borderColor: 'rgba(107,114,128,0.3)' },
};

const Badge: React.FC<BadgeProps> = ({ variant = 'default', children, className }) => {
  const styles = variantStyles[variant];

  return (
    <span
      style={styles}
      className={twMerge(
        clsx(
          'inline-flex items-center gap-1',
          'rounded-full px-2.5 py-0.5',
          'text-xs font-medium border',
          'whitespace-nowrap'
        ),
        className
      )}
    >
      {children}
    </span>
  );
};

export default Badge;
