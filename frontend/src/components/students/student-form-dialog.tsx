"use client";

import { useActionState, useId, useState } from "react";
import { saveStudent } from "@/app/students/actions";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import {
  Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter,
  DialogHeader, DialogTitle, DialogTrigger,
} from "@/components/ui/dialog";
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { Spinner } from "@/components/ui/spinner";
import type { Student, StudentFormState, StudentValues } from "@/lib/student-types";

const fields = [
  { name: "student_number", label: "Student number", type: "text", required: true, maxLength: 30, autoComplete: "off" },
  { name: "full_name", label: "Full name", type: "text", required: true, maxLength: 255, autoComplete: "name" },
  { name: "email", label: "Email", type: "email", required: false, maxLength: 254, autoComplete: "email" },
  { name: "phone", label: "Phone", type: "tel", required: false, maxLength: 30, autoComplete: "tel" },
  { name: "enrollment_date", label: "Enrollment date", type: "date", required: true, autoComplete: "off" },
] as const;

function StudentForm({ student, defaultDate, onSaved }: { student?: Student; defaultDate: string; onSaved: () => void }) {
  const prefix = useId();
  const [state, action, pending] = useActionState(async (previous: StudentFormState, form: FormData) => {
    const result = await saveStudent(student?.id ?? null, previous, form);
    if (result.success) onSaved();
    return result;
  }, {});
  const values: StudentValues = state.values ?? {
    student_number: student?.student_number ?? "", full_name: student?.full_name ?? "",
    email: student?.email ?? "", phone: student?.phone ?? "",
    enrollment_date: student?.enrollment_date ?? defaultDate,
  };

  return (
    <form action={action} aria-busy={pending} className="flex flex-col gap-5">
      <FieldGroup>
        {fields.map(({ name, label, ...input }) => {
          const errors = state.errors?.[name];
          const errorId = prefix + name + "-error";
          return (
            <Field key={name} data-invalid={!!errors}>
              <FieldLabel htmlFor={prefix + name}>{label}{!input.required ? " (optional)" : ""}</FieldLabel>
              <Input {...input} id={prefix + name} name={name} defaultValue={values[name]}
                readOnly={pending} aria-invalid={!!errors} aria-describedby={errors ? errorId : undefined} />
              {name === "student_number" ? <FieldDescription>Letters, numbers, hyphens, and underscores. Must be unique.</FieldDescription> : null}
              {errors ? <FieldError id={errorId} errors={errors.map(message => ({ message }))} /> : null}
            </Field>
          );
        })}
      </FieldGroup>
      {state.error ? <Alert variant="destructive"><AlertDescription>{state.error}</AlertDescription></Alert> : null}
      <DialogFooter>
        <DialogClose render={<Button variant="outline" type="button" disabled={pending} />}>Cancel</DialogClose>
        <Button type="submit" disabled={pending}>
          {pending ? <Spinner data-icon="inline-start" aria-hidden="true" /> : null}
          {pending ? "Saving…" : student ? "Save changes" : "Add student"}
        </Button>
      </DialogFooter>
    </form>
  );
}

export function StudentFormDialog({ student, defaultDate }: { student?: Student; defaultDate: string }) {
  const [open, setOpen] = useState(false);
  const title = student ? "Edit student" : "Add student";

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger render={<Button variant={student ? "outline" : "default"} />}>{title}</DialogTrigger>
      <DialogContent className="max-h-[calc(100svh-2rem)] overflow-y-auto sm:max-w-lg" showCloseButton={false}>
        <DialogHeader>
          <DialogTitle>{title}</DialogTitle>
          <DialogDescription>{student ? "Update this student’s profile." : "Enter the student’s details to create their profile."}</DialogDescription>
        </DialogHeader>
        {open ? <StudentForm student={student} defaultDate={defaultDate} onSaved={() => setOpen(false)} /> : null}
      </DialogContent>
    </Dialog>
  );
}
