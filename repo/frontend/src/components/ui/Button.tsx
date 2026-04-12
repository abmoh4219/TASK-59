import React, { forwardRef } from 'react';
import { Loader2 } from 'lucide-react';
import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

type ButtonVariant = 'primary' | 'secondary' | 'danger' | 'ghost';
type ButtonSize = 'sm' | 'md' | 'lg';

interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: ButtonVariant;
  size?: ButtonSize;
  isLoading?: boolean;
  leftIcon?: React.ReactNode;
  rightIcon?: React.ReactNode;
  fullWidth?: boolean;
  children?: React.ReactNode;
  className?: string;
}

const variantClasses: Record<ButtonVariant, string> = {
  primary:
    'bg-gradient-to-r from-accent to-accent-dark text-white border border-accent/30 ' +
    'hover:from-accent-hover hover:to-accent shadow-glow hover:shadow-glow-lg ' +
    'disabled:from-accent/40 disabled:to-accent-dark/40 disabled:shadow-none',
  secondary:
    'bg-transparent text-white border border-surface-border ' +
    'hover:bg-surface-hover hover:border-accent/50 ' +
    'disabled:text-gray-600 disabled:border-surface-border/50',
  danger:
    'bg-red-500/10 text-red-400 border border-red-500/30 ' +
    'hover:bg-red-500/20 hover:border-red-500/50 hover:text-red-300 ' +
    'disabled:text-red-800 disabled:border-red-900/30',
  ghost:
    'bg-transparent text-gray-400 border border-transparent ' +
    'hover:bg-surface-hover hover:text-white ' +
    'disabled:text-gray-700',
};

const sizeClasses: Record<ButtonSize, string> = {
  sm: 'px-3 py-1.5 text-xs gap-1.5',
  md: 'px-4 py-2 text-sm gap-2',
  lg: 'px-6 py-2.5 text-base gap-2.5',
};

const Button = forwardRef<HTMLButtonElement, ButtonProps>(
  (
    {
      variant = 'primary',
      size = 'md',
      isLoading = false,
      leftIcon,
      rightIcon,
      fullWidth = false,
      children,
      className,
      disabled,
      type = 'button',
      ...rest
    },
    ref
  ) => {
    const isDisabled = disabled || isLoading;

    return (
      <button
        ref={ref}
        type={type}
        disabled={isDisabled}
        className={twMerge(
          clsx(
            // Base styles
            'inline-flex items-center justify-center font-medium rounded-lg',
            'transition-all duration-150 ease-in-out',
            'focus:outline-none focus:ring-2 focus:ring-accent/50 focus:ring-offset-2 focus:ring-offset-surface',
            'disabled:cursor-not-allowed',
            // Variant
            variantClasses[variant],
            // Size
            sizeClasses[size],
            // Full width
            fullWidth && 'w-full',
            className
          )
        )}
        {...rest}
      >
        {isLoading ? (
          <Loader2 className="animate-spin shrink-0" size={size === 'sm' ? 14 : size === 'lg' ? 18 : 16} />
        ) : (
          leftIcon && <span className="shrink-0">{leftIcon}</span>
        )}
        {children && <span>{children}</span>}
        {!isLoading && rightIcon && <span className="shrink-0">{rightIcon}</span>}
      </button>
    );
  }
);

Button.displayName = 'Button';

export default Button;
