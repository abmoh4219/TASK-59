import React from 'react';
import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

interface EmptyStateProps {
  /** Icon node or component element rendered above the title */
  icon?: React.ReactNode;
  title: string;
  description?: string;
  /** Optional action element (e.g. a Button) rendered below the description */
  action?: React.ReactNode;
  className?: string;
}

/**
 * Centered empty state with icon, title, description and optional CTA.
 * Used when a table/list has no data or a page has no content yet.
 */
const EmptyState: React.FC<EmptyStateProps> = ({
  icon,
  title,
  description,
  action,
  className,
}) => (
  <div
    className={twMerge(
      clsx(
        'flex flex-col items-center justify-center text-center',
        'py-16 px-6 gap-4',
        className
      )
    )}
  >
    {icon && (
      <div className="flex items-center justify-center w-16 h-16 rounded-full bg-surface-hover border border-surface-border text-gray-500 mb-2">
        {icon}
      </div>
    )}
    <h3 className="text-base font-semibold text-white">{title}</h3>
    {description && (
      <p className="text-sm text-gray-500 max-w-sm">{description}</p>
    )}
    {action && <div className="mt-2">{action}</div>}
  </div>
);

export default EmptyState;
