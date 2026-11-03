"use client";

import { useActionState, useState } from "react";
import { changeStudentArchive } from "@/app/students/actions";
import { Alert, AlertDescription } from "@/components/ui/alert";
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
  AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger,
} from "@/components/ui/alert-dialog";
import { Button } from "@/components/ui/button";
import { Spinner } from "@/components/ui/spinner";
import type { Student, StudentFormState } from "@/lib/student-types";

export function StudentArchiveAction({ student }: { student: Student }) {
  const [open, setOpen] = useState(false);
  const operation = student.status === "archived" ? "restore" : "archive";
  const title = operation === "archive" ? "Archive student" : "Restore student";
  const [state, action, pending] = useActionState(async (): Promise<StudentFormState> => {
    const result = await changeStudentArchive(student.id, operation);
    if (result.success) setOpen(false);
    return result;
  }, {});

  return (
    <AlertDialog open={open} onOpenChange={setOpen}>
      <AlertDialogTrigger render={<Button variant="outline" />}>{title}</AlertDialogTrigger>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>{title}?</AlertDialogTitle>
          <AlertDialogDescription>
            {operation === "archive" ? `${student.full_name} will move to archived records. You can restore this profile later.` : `${student.full_name} will return to the active student list.`}
          </AlertDialogDescription>
        </AlertDialogHeader>
        {state.error ? <Alert variant="destructive"><AlertDescription>{state.error}</AlertDescription></Alert> : null}
        <form action={action} aria-busy={pending}>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={pending}>Cancel</AlertDialogCancel>
            <AlertDialogAction type="submit" disabled={pending}>
              {pending ? <Spinner data-icon="inline-start" aria-hidden="true" /> : null}
              {pending ? "Saving…" : title}
            </AlertDialogAction>
          </AlertDialogFooter>
        </form>
      </AlertDialogContent>
    </AlertDialog>
  );
}
