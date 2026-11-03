import type { Metadata } from "next";
import { TooltipProvider } from "@/components/ui/tooltip";
import { DisplayPreferences } from "@/components/layout/display-preferences";
import "./globals.css";

export const metadata: Metadata = {
  title: "Education Management System",
  description: "Your school community, in one workspace.",
};

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    <html lang="en" suppressHydrationWarning>
      <head>
        <link rel="preload" href="/fonts/inter-variable.woff2" as="font" type="font/woff2" crossOrigin="anonymous" />
        <script dangerouslySetInnerHTML={{ __html: "try{const p=(localStorage.getItem('ems-display-preferences')||'en|light').split('|');document.documentElement.classList.toggle('dark',p[1]==='dark');document.documentElement.lang=p[0]==='ms'?'ms':'en'}catch{}" }} />
      </head>
      <body><DisplayPreferences><TooltipProvider>{children}</TooltipProvider></DisplayPreferences></body>
    </html>
  );
}
