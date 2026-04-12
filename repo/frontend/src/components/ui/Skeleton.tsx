import React from 'react';
import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

interface SkeletonProps {
  width?: string | number;
  height?: string | number;
  className?: string;
  /** Use 'full' for rounded-full (circle/pill), 'xl' for rounded-xl, etc. Default: 'md' */
  rounded?: 'none' | 'sm' | 'md' | 'lg' | 'xl' | 'full';
}

const roundedClasses: Record<NonNullable<SkeletonProps['rounded']>, string> = {
  none: 'rounded-none',
  sm:   'rounded-sm',
  md:   'rounded-md',
  lg:   'rounded-lg',
  xl:   'rounded-xl',
  full: 'rounded-full',
};

/**
 * Shimmer loading placeholder.
 * Composes bg-surface-hover with the .shimmer animation defined in index.css.
 */
const Skeleton: React.FC<SkeletonProps> = ({
  width,
  height,
  className,
  rounded = 'md',
}) => {
  const style: React.CSSProperties = {};
  if (width !== undefined) style.width = typeof width === 'number' ? `${width}px` : width;
  if (height !== undefined) style.height = typeof height === 'number' ? `${height}px` : height;

  return (
    <div
      style={style}
      aria-hidden="true"
      className={twMerge(
        clsx(
          'bg-surface-hover shimmer',
          roundedClasses[rounded],
          className
        )
      )}
    />
  );
};

export default Skeleton;
