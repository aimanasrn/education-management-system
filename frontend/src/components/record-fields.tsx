"use client";

import { Checkbox } from "@/components/ui/checkbox";
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel, FieldLegend, FieldSet } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { weekdays, type FieldSpec, type Hours, type Option } from "@/lib/foundation-config";

export function RecordFields({ fields, values, options, errors, editing, onChange }: {
  fields: FieldSpec[]; values: Record<string, unknown>; options: Record<string, Option[]>; errors?: Record<string, string[]>; editing: boolean; onChange: (key: string, value: unknown) => void;
}) {
  return <FieldGroup>{fields.filter(field => !(editing && field.type === "password")).map(field => {
    const invalid = Object.keys(errors ?? {}).some(key => key === field.key || key.startsWith(field.key + "."));
    const messages = Object.entries(errors ?? {}).filter(([key]) => key === field.key || key.startsWith(field.key + ".")).flatMap(([, messages]) => messages);
    const value = values[field.key];
    const id = "record-" + field.key;
    let choices = options[field.key] ?? [];
    if (field.key === "status") choices = [{ value: "active", label: "Active" }, { value: "inactive", label: "Inactive" }];
    if (field.key === "kind") choices = [{ value: "holiday", label: "Holiday" }, { value: "closure", label: "Closure" }];
    if (["building_id", "room_id"].includes(field.key) && values.campus_id !== undefined) choices = choices.filter(option => String(option.campus_id) === String(values.campus_id));
    if (field.key === "academic_term_id") choices = choices.filter(option => String(option.academic_year_id) === String(values.academic_year_id));
    if (field.key === "subject_id") choices = choices.filter(option => String(option.programme_id) === String(values.programme_id));
    if (field.key === "teacher_ids") choices = choices.filter(option => option.campus_ids?.includes(Number(values.campus_id)));
    if (field.type === "multi") {
      const selected = Array.isArray(value) ? value : [];
      return <FieldSet key={field.key} aria-invalid={invalid} aria-describedby={invalid ? id + "-error" : undefined}>
        <FieldLegend>{field.label}</FieldLegend>
        <FieldGroup>{choices.map(option => <Field orientation="horizontal" key={option.value} data-invalid={invalid}>
          <Checkbox id={id + "-" + option.value} checked={selected.includes(option.value)} aria-invalid={invalid} onCheckedChange={checked => onChange(field.key, checked ? [...selected, option.value] : selected.filter(item => item !== option.value))} />
          <FieldLabel htmlFor={id + "-" + option.value}>{option.label}</FieldLabel>
        </Field>)}</FieldGroup>
        {choices.length === 0 ? <FieldDescription>No available options. Add the required records or campus assignments first.</FieldDescription> : null}
        <FieldError id={id + "-error"} errors={messages.map(message => ({ message }))} />
      </FieldSet>;
    }
    if (field.type === "hours") {
      const hours = value as Hours;
      return <FieldSet key={field.key}><FieldLegend>Operating hours · Kuala Lumpur</FieldLegend><FieldDescription>Leave closed days checked. Open days need an opening and closing time.</FieldDescription>
        <FieldGroup>{weekdays.map(([day, label]) => {
          const dayHours = hours[day];
          const update = (changes: Partial<Hours[string]>) => onChange(field.key, { ...hours, [day]: { ...dayHours, ...changes } });
          return <FieldSet key={day}><FieldLegend variant="label">{label}</FieldLegend>
            <Field orientation="horizontal"><Checkbox id={id + "-" + day} checked={dayHours.closed} onCheckedChange={closed => update({ closed })} /><FieldLabel htmlFor={id + "-" + day}>Closed</FieldLabel></Field>
            {!dayHours.closed ? <FieldGroup className="grid grid-cols-2 gap-3">{(["opens", "closes"] as const).map(part => {
              const key = field.key + "." + day + "." + part;
              return <Field key={part} data-invalid={!!errors?.[key]}><FieldLabel htmlFor={id + "-" + day + "-" + part}>{part === "opens" ? "Opens" : "Closes"}</FieldLabel><Input id={id + "-" + day + "-" + part} type="time" value={dayHours[part] ?? ""} aria-invalid={!!errors?.[key]} onChange={event => update({ [part]: event.target.value })} /><FieldError errors={errors?.[key]?.map(message => ({ message }))} /></Field>;
            })}</FieldGroup> : null}
          </FieldSet>;
        })}</FieldGroup><FieldError errors={messages.map(message => ({ message }))} />
      </FieldSet>;
    }
    if (field.type === "boolean") return <Field key={field.key} orientation="horizontal" data-invalid={invalid}><Checkbox id={id} checked={value === true} onCheckedChange={checked => onChange(field.key, checked)} /><FieldLabel htmlFor={id}>{field.label}</FieldLabel><FieldError errors={messages.map(message => ({ message }))} /></Field>;
    const disabled = editing && field.key.endsWith("_id");
    return <Field key={field.key} data-invalid={invalid}>
      <FieldLabel htmlFor={id}>{field.label}{field.optional ? " (optional)" : ""}</FieldLabel>
      {field.type === "select" ? <Select value={value === null || value === "" ? "" : String(value)} items={[{ value: "", label: field.optional ? "None" : "Choose…" }, ...choices.map(option => ({ value: String(option.value), label: option.label }))]} disabled={disabled} onValueChange={next => onChange(field.key, next === "" ? null : field.key.endsWith("_id") ? Number(next) : next)}>
        <SelectTrigger id={id} className="w-full" aria-invalid={invalid} aria-describedby={invalid ? id + "-error" : undefined}><SelectValue /></SelectTrigger>
        <SelectContent><SelectGroup><SelectItem value="">{field.optional ? "None" : "Choose…"}</SelectItem>{choices.map(option => <SelectItem key={option.value} value={String(option.value)}>{option.label}</SelectItem>)}</SelectGroup></SelectContent>
      </Select> : field.type === "textarea" ? <Textarea id={id} value={String(value ?? "")} aria-invalid={invalid} onChange={event => onChange(field.key, event.target.value)} /> :
      <Input id={id} type={field.type === "facilities" ? "text" : field.type ?? "text"} value={field.type === "facilities" && Array.isArray(value) ? value.join(", ") : String(value ?? "")} autoComplete={field.type === "password" ? "new-password" : "off"} aria-invalid={invalid} aria-describedby={invalid ? id + "-error" : undefined} onChange={event => onChange(field.key, field.type === "number" ? event.target.value === "" ? "" : Number(event.target.value) : event.target.value)} />}
      {field.type === "facilities" ? <FieldDescription>Separate facilities with commas.</FieldDescription> : null}
      {field.type === "password" ? <FieldDescription>At least 12 characters, up to 72 bytes. Share it through your established secure channel.</FieldDescription> : null}
      {disabled ? <FieldDescription>Relationship fixed to preserve history.</FieldDescription> : null}
      <FieldError id={id + "-error"} errors={messages.map(message => ({ message }))} />
    </Field>;
  })}</FieldGroup>;
}
