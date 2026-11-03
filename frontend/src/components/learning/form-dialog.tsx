"use client";
import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { learningMutation } from "@/app/learning-actions";
import { RecordFields } from "@/components/record-fields";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
} from "@/components/ui/dialog";
import { Spinner } from "@/components/ui/spinner";
import type { FieldSpec } from "@/lib/foundation-config";
import type {
  LearningOperation,
  LearningOptions,
  LearningResult,
} from "@/lib/learning-types";
export function LearningForm({
  title,
  description,
  operation,
  id = null,
  initial,
  fields,
  options = {},
  redirectTo,
}: {
  title: string;
  description: string;
  operation: LearningOperation;
  id?: number | null;
  initial: Record<string, unknown>;
  fields: FieldSpec[];
  options?: LearningOptions;
  redirectTo?: string;
}) {
  const [open, setOpen] = useState(false);
  const [values, setValues] = useState(initial);
  const [state, setState] = useState<LearningResult>({});
  const [pending, start] = useTransition();
  const router = useRouter();
  function change(key: string, value: unknown) {
    setValues((previous) => {
      const next = { ...previous, [key]: value };
      if (key === "link_type") next.user_id = "";
      if (key === "school_class_id") {
        const choice = options.school_class_id?.find(
          (o) => String(o.value) === String(value),
        );
        if (choice?.starts_on && !id) next.starts_on = choice.starts_on;
        if (choice?.ends_on) next.ends_on = choice.ends_on;
      }
      return next;
    });
  }
  function save() {
    start(async () => {
      try {
        const result = await learningMutation(operation, id, values);
        setState(result);
        if (result.success) {
          setOpen(false);
          router.refresh();
          if (redirectTo) router.push(redirectTo + (result.id ?? ""));
        }
      } catch {
        setState({
          error: "The request failed. Your values have been kept. Try again.",
        });
      }
    });
  }
  return (
    <>
      <Button
        variant={id ? "outline" : "default"}
        onClick={() => {
          setValues(initial);
          setState({});
          setOpen(true);
        }}
      >
        {title}
      </Button>
      <Dialog
        open={open}
        onOpenChange={(next) => {
          if (!pending) setOpen(next);
        }}
      >
        <DialogContent className="max-h-[90svh] overflow-y-auto sm:max-w-xl">
          <DialogHeader>
            <DialogTitle>{title}</DialogTitle>
            <DialogDescription>{description}</DialogDescription>
          </DialogHeader>
          <form
            onSubmit={(event) => {
              event.preventDefault();
              save();
            }}
            className="flex flex-col gap-5"
          >
            <fieldset disabled={pending} className="contents">
              <RecordFields
                fields={fields
                  .filter((field) =>
                    values.change_type !== "transfer"
                      ? !["school_class_id", "ends_on"].includes(field.key) ||
                        operation !== "enrolment-change"
                      : true,
                  )
                  .filter((field) =>
                    values.link_type === "student"
                      ? !["relationship", "emergency_contact"].includes(
                          field.key,
                        )
                      : true,
                  )}
                values={values}
                errors={state.errors}
                options={
                  operation === "link"
                    ? {
                        ...options,
                        user_id:
                          options[
                            values.link_type === "student"
                              ? "student_user_id"
                              : "guardian_user_id"
                          ] ?? [],
                      }
                    : options
                }
                editing={false}
                onChange={change}
              />
            </fieldset>
            {state.error ? (
              <Alert variant="destructive" role="alert">
                <AlertDescription>
                  {state.error}
                  {state.errors
                    ? Object.entries(state.errors)
                        .filter(
                          ([key]) => !fields.some((field) => field.key === key),
                        )
                        .map(([key, messages]) => (
                          <p key={key}>{messages.join(" ")}</p>
                        ))
                    : null}
                </AlertDescription>
              </Alert>
            ) : null}
            <DialogFooter>
              <Button
                type="button"
                variant="outline"
                disabled={pending}
                onClick={() => setOpen(false)}
              >
                Cancel
              </Button>
              <Button type="submit" disabled={pending}>
                {pending ? <Spinner /> : null}
                {pending ? "Saving…" : "Save"}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </>
  );
}
