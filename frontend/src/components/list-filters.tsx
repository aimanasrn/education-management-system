"use client";

import { useEffect, useRef, useState, useTransition } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { cn } from "cn";
import { Button } from "@/components/ui/button";
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Spinner } from "@/components/ui/spinner";

type Values = Record<string, string>;
export type ListFilter = { name: string; label: string; defaultValue: string; options: { value: string; label: string }[] };

function filterKey(values: Values) {
  return JSON.stringify(Object.keys(values).sort().map(key => [key, key === "q" ? values[key].trim() : values[key]]));
}

export function ListFilters({ path, filters, selects = [], searchLabel = "Search", placeholder = "Name or code" }: {
  path: string; filters: Values; selects?: ListFilter[]; searchLabel?: string; placeholder?: string;
}) {
  const router = useRouter();
  const searchParams = useSearchParams();
  const timer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const composing = useRef(false);
  const [pending, startTransition] = useTransition();
  const defaults = Object.fromEntries([["q", ""], ...selects.map(filter => [filter.name, filter.defaultValue])]);
  const defaultSchema = JSON.stringify(defaults);
  const committed = Object.fromEntries(Object.keys(defaults).map(key => [key, filters[key] ?? defaults[key]]));
  const committedKey = filterKey(committed);
  const [state, setState] = useState(() => ({ source: committedKey, values: committed, requested: [] as string[], externalRevision: 0 }));

  if (state.source !== committedKey) {
    const ownCommit = state.requested.indexOf(committedKey);
    setState({
      source: committedKey,
      values: ownCommit >= 0 ? state.values : committed,
      requested: ownCommit >= 0 ? state.requested.slice(ownCommit + 1) : [],
      externalRevision: state.externalRevision + (ownCommit >= 0 ? 0 : 1),
    });
  }

  useEffect(() => {
    if (timer.current !== null) clearTimeout(timer.current);
  }, [state.externalRevision]);

  useEffect(() => {
    const valuesFromUrl = (query: URLSearchParams) => {
      const schema: Values = JSON.parse(defaultSchema);
      return Object.fromEntries(Object.keys(schema).map(key => [key, query.get(key) ?? schema[key]]));
    };
    const syncHistory = () => {
      if (timer.current !== null) clearTimeout(timer.current);
      composing.current = false;
      const values = valuesFromUrl(new URLSearchParams(window.location.search));
      setState(previous => ({ ...previous, values, requested: [] }));
    };
    const cancelForLink = (event: MouseEvent) => {
      if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
      const anchor = event.target instanceof Element ? event.target.closest<HTMLAnchorElement>("a[href]") : null;
      if (!anchor || anchor.target === "_blank" || anchor.hasAttribute("download")) return;
      const next = new URL(anchor.href, window.location.href);
      if (next.origin !== window.location.origin || (next.pathname === window.location.pathname && next.search === window.location.search)) return;
      if (timer.current !== null) clearTimeout(timer.current);
      composing.current = false;
      setState(previous => ({ ...previous, values: next.pathname === path ? valuesFromUrl(next.searchParams) : previous.values, requested: [] }));
    };
    window.addEventListener("popstate", syncHistory);
    document.addEventListener("click", cancelForLink, true);
    return () => {
      window.removeEventListener("popstate", syncHistory);
      document.removeEventListener("click", cancelForLink, true);
      if (timer.current !== null) clearTimeout(timer.current);
    };
  }, [defaultSchema, path]);

  function navigate(values: Values, history: "push" | "replace") {
    if (timer.current !== null) clearTimeout(timer.current);
    const params = new URLSearchParams(searchParams.toString());
    for (const key of Object.keys(defaults)) {
      const value = key === "q" ? values[key].trim() : values[key];
      if (!value || value === defaults[key]) params.delete(key);
      else params.set(key, value);
    }
    params.delete("page");
    const query = params.toString();
    const href = path + (query ? "?" + query : "");
    const requestedKey = filterKey(values);
    setState(previous => ({
      ...previous,
      values,
      requested: requestedKey === previous.source && !pending ? previous.requested : [...previous.requested.filter(key => key !== requestedKey), requestedKey].slice(-20),
    }));
    if (query !== searchParams.toString() || pending) {
      startTransition(() => router[history](href, { scroll: false }));
    }
  }

  function change(name: string, value: string) {
    if (timer.current !== null) clearTimeout(timer.current);
    const values = { ...state.values, [name]: value };
    setState(previous => ({ ...previous, values }));
    if (name !== "q") {
      navigate(values, "push");
    } else if (!composing.current) {
      timer.current = setTimeout(() => navigate(values, "replace"), 350);
    }
  }

  const updating = pending || filterKey(state.values) !== committedKey;
  const canClear = filterKey(state.values) !== filterKey(defaults);

  return <form role="search" aria-label="Filter records" onSubmit={event => {
    event.preventDefault();
    if (!composing.current) navigate(state.values, "replace");
  }}>
    <FieldGroup className={cn("grid gap-4", {
      "sm:grid-cols-[minmax(0,1fr)_12rem]": selects.length === 1,
      "sm:grid-cols-2 lg:grid-cols-3": selects.length === 2,
      "sm:grid-cols-2 lg:grid-cols-4": selects.length >= 3,
    })}>
      <Field>
        <FieldLabel htmlFor="list-search">{searchLabel}</FieldLabel>
        <Input id="list-search" name="q" type="search" value={state.values.q} maxLength={100} placeholder={placeholder}
          onChange={event => change("q", event.target.value)}
          onCompositionStart={() => { composing.current = true; if (timer.current !== null) clearTimeout(timer.current); }}
          onCompositionEnd={event => { composing.current = false; change("q", event.currentTarget.value); }} />
      </Field>
      {selects.map(filter => <Field key={filter.name}>
        <FieldLabel htmlFor={"list-" + filter.name}>{filter.label}</FieldLabel>
        <Select name={filter.name} value={state.values[filter.name]} items={filter.options} onValueChange={value => change(filter.name, value ?? filter.defaultValue)}>
          <SelectTrigger id={"list-" + filter.name} className="w-full"><SelectValue /></SelectTrigger>
          <SelectContent><SelectGroup>{filter.options.map(option => <SelectItem key={option.value} value={option.value}>{option.label}</SelectItem>)}</SelectGroup></SelectContent>
        </Select>
      </Field>)}
    </FieldGroup>
    <div className="mt-3 flex min-h-8 items-center justify-between gap-3">
      <p role="status" aria-live="polite" className="flex items-center gap-2 text-xs text-muted-foreground">{updating ? <><Spinner aria-hidden="true" />Updating results…</> : "Results update automatically."}</p>
      {canClear ? <Button variant="ghost" size="sm" type="button" onClick={() => navigate(defaults, "push")}>Clear</Button> : null}
    </div>
  </form>;
}
