"use client";

import { useEffect, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { ArrowUpRightIcon, SearchIcon } from "lucide-react";
import { searchWorkspace } from "@/app/workspace-actions";
import { Button } from "@/components/ui/button";
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from "@/components/ui/command";
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Kbd } from "@/components/ui/kbd";
import { Spinner } from "@/components/ui/spinner";
import { useDisplay } from "@/components/layout/display-preferences";
import { useWorkspace } from "@/components/layout/workspace-context";
import { navigationLinks } from "@/lib/workspace-navigation";
import type { SearchMatch } from "@/lib/workspace-types";

export function GlobalSearch() {
  const { user, campusId, href } = useWorkspace();
  const { t } = useDisplay();
  const router = useRouter();
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState("");
  const [matches, setMatches] = useState<SearchMatch[]>([]);
  const [failed, setFailed] = useState(false);
  const [completedQuery, setCompletedQuery] = useState("");
  const [pending, startTransition] = useTransition();
  const links = navigationLinks(user.permissions).filter(item => (t(item.key) + " " + item.key).toLowerCase().includes(query.toLowerCase().trim()));
  function changeOpen(next: boolean) {
    setOpen(next); setQuery(""); setMatches([]); setFailed(false); setCompletedQuery("");
  }
  useEffect(() => {
    const shortcut = (event: KeyboardEvent) => {
      if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === "k") {
        event.preventDefault(); setOpen(previous => !previous); setQuery(""); setMatches([]); setFailed(false); setCompletedQuery("");
      }
    };
    document.addEventListener("keydown", shortcut);
    return () => document.removeEventListener("keydown", shortcut);
  }, []);
  useEffect(() => {
    if (!open || query.trim().length < 2) return;
    let active = true;
    const timer = setTimeout(() => {
      startTransition(async () => {
        try {
          const result = await searchWorkspace(query, campusId);
          if (active) { setMatches(result.matches); setFailed(result.failed); setCompletedQuery(query); }
        } catch { if (active) { setFailed(true); setCompletedQuery(query); } }
      });
    }, 300);
    return () => { active = false; clearTimeout(timer); };
  }, [query, open, campusId]);
  function visit(path: string) { changeOpen(false); router.push(path); }
  const labels: Record<string, string> = { students: "studentProfiles", users: "staffUsers", campuses: "campusDirectory", closures: "calendar" };
  const searching = pending || (query.trim().length >= 2 && completedQuery !== query);

  return <>
    <Button variant="search" className="h-10 min-w-0 flex-1 gap-3 px-3 sm:max-w-md" aria-label={t("searchTitle")} onClick={() => changeOpen(true)}>
      <SearchIcon data-icon="inline-start" /><span className="hidden min-w-0 flex-1 truncate text-left sm:block">{t("search")}</span><Kbd className="ml-auto hidden lg:inline-flex">Ctrl + K</Kbd>
    </Button>
    <Dialog open={open} onOpenChange={changeOpen}>
      <DialogContent className="max-w-xl gap-0 p-2" showCloseButton={false}>
        <DialogHeader className="sr-only"><DialogTitle>{t("searchTitle")}</DialogTitle><DialogDescription>{t("searchDescription")}</DialogDescription></DialogHeader>
        <Command shouldFilter={false}>
          <CommandInput placeholder={t("searchPlaceholder")} value={query} onValueChange={value => { setQuery(value); setMatches([]); setFailed(false); }} aria-label={t("searchTitle")} />
          <CommandList>
            <CommandEmpty>{searching ? t("searching") : t("noResults")}</CommandEmpty>
            {links.length ? <CommandGroup heading={t("pages")}>{links.map(item => <CommandItem key={item.key} value={item.key} onSelect={() => visit(href(item.href!))}><item.icon /><span>{t(item.key)}</span><ArrowUpRightIcon className="ml-auto" /></CommandItem>)}</CommandGroup> : null}
            {matches.length ? <CommandGroup heading={t("records")}>{matches.map(match => <CommandItem key={match.id} value={match.id} onSelect={() => visit(match.href)}><SearchIcon /><div className="flex min-w-0 flex-col"><span className="truncate">{match.name}</span><span className="text-xs text-muted-foreground">{t(labels[match.module] ?? match.module)} · {match.description}</span></div><ArrowUpRightIcon className="ml-auto" /></CommandItem>)}</CommandGroup> : null}
          </CommandList>
        </Command>
        {searching ? <p role="status" className="flex items-center gap-2 px-3 py-2 text-xs text-muted-foreground"><Spinner />{t("searching")}</p> : null}
        {failed ? <p role="alert" className="px-3 py-2 text-xs text-destructive">{t("searchUnavailable")}</p> : null}
      </DialogContent>
    </Dialog>
  </>;
}
