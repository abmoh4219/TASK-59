import React from 'react';
import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

// ─── Card Root ────────────────────────────────────────────────────────────────

interface CardProps {
  children?: React.ReactNode;
  className?: string;
  /** Adds hover shadow and subtle border brightening on hover */
  hoverable?: boolean;
}

const CardRoot: React.FC<CardProps> = ({ children, className, hoverable = false }) => (
  <div
    className={twMerge(
      clsx(
        'bg-surface-card border border-surface-border rounded-xl',
        hoverable && 'transition-all duration-200 hover:shadow-glow hover:border-accent/30 cursor-pointer',
        className
      )
    )}
  >
    {children}
  </div>
);

// ─── Card.Header ─────────────────────────────────────────────────────────────

interface CardHeaderProps {
  children?: React.ReactNode;
  className?: string;
}

const CardHeader: React.FC<CardHeaderProps> = ({ children, className }) => (
  <div
    className={twMerge(
      clsx('px-6 py-4 border-b border-surface-border flex items-center justify-between gap-4', className)
    )}
  >
    {children}
  </div>
);

// ─── Card.Body ────────────────────────────────────────────────────────────────

interface CardBodyProps {
  children?: React.ReactNode;
  className?: string;
}

const CardBody: React.FC<CardBodyProps> = ({ children, className }) => (
  <div className={twMerge(clsx('px-6 py-4', className))}>{children}</div>
);

// ─── Card.Footer ─────────────────────────────────────────────────────────────

interface CardFooterProps {
  children?: React.ReactNode;
  className?: string;
}

const CardFooter: React.FC<CardFooterProps> = ({ children, className }) => (
  <div
    className={twMerge(
      clsx('px-6 py-4 border-t border-surface-border flex items-center justify-end gap-3', className)
    )}
  >
    {children}
  </div>
);

// ─── Composed export ─────────────────────────────────────────────────────────

const Card = Object.assign(CardRoot, {
  Header: CardHeader,
  Body: CardBody,
  Footer: CardFooter,
});

export default Card;
