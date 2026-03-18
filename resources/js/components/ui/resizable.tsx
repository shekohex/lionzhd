import { GripVertical } from "lucide-react"
import * as ResizablePrimitive from "react-resizable-panels"

import { cn } from "@/lib/utils"

const ResizablePanelGroup = ({
  className,
  ...props
}: React.ComponentProps<typeof ResizablePrimitive.Group>) => (
  <ResizablePrimitive.Group
    className={cn(
      "flex h-full w-full data-[panel-group-direction=vertical]:flex-col",
      className
    )}
    {...props}
  />
)

const ResizablePanel = ResizablePrimitive.Panel

const ResizableHandle = ({
  withHandle,
  className,
  ...props
}: React.ComponentProps<typeof ResizablePrimitive.Separator> & {
  withHandle?: boolean
}) => (
  <ResizablePrimitive.Separator
    className={cn(
      "relative group flex w-2 items-center justify-center bg-border/40 hover:bg-primary/50 active:bg-primary transition-all after:absolute after:inset-y-0 after:left-1/2 after:w-10 after:-translate-x-1/2 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring focus-visible:ring-offset-1 data-[panel-group-direction=vertical]:h-2 data-[panel-group-direction=vertical]:w-full data-[panel-group-direction=vertical]:after:left-0 data-[panel-group-direction=vertical]:after:h-10 data-[panel-group-direction=vertical]:after:w-full data-[panel-group-direction=vertical]:after:-translate-y-1/2 data-[panel-group-direction=vertical]:after:translate-x-0 [&[data-panel-group-direction=vertical]>div]:rotate-90 z-50",
      className
    )}
    {...props}
  >
    {withHandle && (
      <div className="z-10 flex h-8 w-5 items-center justify-center rounded-md border bg-background shadow-lg ring-1 ring-border/50 transition-transform group-hover:scale-110 group-active:scale-95 group-hover:bg-accent">
        <GripVertical className="h-4 w-4 text-muted-foreground" />
      </div>
    )}
  </ResizablePrimitive.Separator>
)


export { ResizablePanelGroup, ResizablePanel, ResizableHandle }
