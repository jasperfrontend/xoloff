import { defineComponent } from 'vue';

/**
 * Stand-ins for the shadcn select wrappers.
 *
 * The real ones are built on reka-ui, which renders its listbox into a portal
 * and expects pointer events a jsdom test cannot sensibly produce. These render
 * their slots and declare the same event, so a test can choose a value by
 * emitting it, which is exactly what the real component does once someone
 * clicks an option.
 */
function passthrough(name: string) {
  return defineComponent({
    name: `${name}Stub`,
    setup:
      (_, { slots }) =>
      () =>
        slots.default?.(),
  });
}

export function selectStub() {
  return {
    Select: defineComponent({
      name: 'SelectStub',
      props: {
        modelValue: { type: [String, Number], default: undefined },
        defaultValue: { type: [String, Number], default: undefined },
        name: { type: String, default: undefined },
      },
      emits: ['update:modelValue'],
      setup:
        (_, { slots }) =>
        () =>
          slots.default?.(),
    }),
    SelectTrigger: passthrough('SelectTrigger'),
    SelectValue: defineComponent({
      name: 'SelectValueStub',
      props: { placeholder: { type: String, default: '' } },
      setup: (props) => () => props.placeholder,
    }),
    SelectContent: passthrough('SelectContent'),
    SelectItem: defineComponent({
      name: 'SelectItemStub',
      props: { value: { type: [String, Number], default: '' } },
      setup:
        (_, { slots }) =>
        () =>
          slots.default?.(),
    }),
    SelectGroup: passthrough('SelectGroup'),
    SelectLabel: passthrough('SelectLabel'),
    SelectSeparator: passthrough('SelectSeparator'),
    SelectItemText: passthrough('SelectItemText'),
    SelectScrollUpButton: passthrough('SelectScrollUpButton'),
    SelectScrollDownButton: passthrough('SelectScrollDownButton'),
  };
}
