import { useState } from 'react';
import type { ComponentProps, ReactNode } from 'react';
import { AlertDialog, Button, Flex } from '@radix-ui/themes';

/**
 * A destructive-action button guarded by an in-app confirmation dialog.
 *
 * Canvas embeds page extensions in an iframe whose sandbox attribute omits
 * `allow-modals`, so `window.confirm()` is ignored by the browser and returns
 * false. Any button gated on its return value therefore does nothing at all.
 *
 * @see ui/src/components/extensions/ExtensionPage.tsx in the canvas module.
 */
export default function ConfirmButton({
  title,
  description,
  confirmLabel = 'Delete',
  onConfirm,
  children,
  ...triggerProps
}: {
  title: string;
  description: string;
  confirmLabel?: string;
  onConfirm: () => Promise<void> | void;
  children: ReactNode;
} & ComponentProps<typeof Button>) {
  const [open, setOpen] = useState(false);
  const [busy, setBusy] = useState(false);

  return (
    <AlertDialog.Root open={open} onOpenChange={setOpen}>
      <AlertDialog.Trigger>
        <Button {...triggerProps}>{children}</Button>
      </AlertDialog.Trigger>
      <AlertDialog.Content maxWidth="480px">
        <AlertDialog.Title>{title}</AlertDialog.Title>
        <AlertDialog.Description size="2">{description}</AlertDialog.Description>
        <Flex gap="3" mt="4" justify="end">
          <AlertDialog.Cancel>
            <Button variant="soft" color="gray" disabled={busy}>
              Cancel
            </Button>
          </AlertDialog.Cancel>
          <Button
            color="red"
            loading={busy}
            onClick={async () => {
              setBusy(true);
              try {
                await onConfirm();
              }
              finally {
                setBusy(false);
                setOpen(false);
              }
            }}
          >
            {confirmLabel}
          </Button>
        </Flex>
      </AlertDialog.Content>
    </AlertDialog.Root>
  );
}
