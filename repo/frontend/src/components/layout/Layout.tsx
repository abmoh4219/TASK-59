import type { ReactNode } from 'react';
import Sidebar from './Sidebar';
import TopBar from './TopBar';

interface LayoutProps {
  children: ReactNode;
}

export default function Layout({ children }: LayoutProps) {
  return (
    <div className="min-h-screen bg-surface">
      <Sidebar />
      <div className="ml-60">
        <TopBar />
        <main className="p-6">{children}</main>
      </div>
    </div>
  );
}
