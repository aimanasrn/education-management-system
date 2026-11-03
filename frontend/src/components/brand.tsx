"use client";

import { GraduationCapIcon } from "lucide-react";
import { cn } from "@/lib/utils";
import { useDisplay } from "@/components/layout/display-preferences";

export function Brand({ stacked = false }: { stacked?: boolean }) {
  const { t } = useDisplay();
  return <div className={cn("flex items-center gap-3", stacked && "w-full")}>
    <GraduationCapIcon className="size-10 shrink-0 text-primary" strokeWidth={2.1} aria-hidden="true" />
    <div className="flex min-w-0 flex-col gap-0.5">
      <span className="text-xl font-bold tracking-tight text-foreground">EMS</span>
      <span className="max-w-48 text-[10px] leading-relaxed text-muted-foreground">{t("educationSystem")}</span>
    </div>
  </div>;
}
