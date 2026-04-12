import type { ReactNode } from 'react';
import Sidebar from './Sidebar';
import TopBar from './TopBar';

interface LayoutProps {
  children: ReactNode;
}

export default function Layout({ children }: LayoutProps) {
  return (
    <div className="min-h-screen mesh-bg relative">
      <Sidebar />
      <div className="ml-64">
        <TopBar />
        <main className="p-8 max-w-[1600px] animate-fade-in">{children}</main>
      </div>
    </div>
  );
}
