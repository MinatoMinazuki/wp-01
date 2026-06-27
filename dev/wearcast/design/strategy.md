# Wearcast Direction

## Product stance

This should not feel like a government weather form.
It should feel like a sharp daily decision tool:

- open once in the morning
- understand today's condition in 3 seconds
- get one confident outfit answer
- optionally record the result at night

## UI direction

Keyword set:

- editorial
- practical
- calm
- warm
- high-contrast

Avoid:

- dashboard clutter
- generic admin cards
- flat white backgrounds
- oversized forms on the first screen

## Core screen structure

### 1. Today

Goal:

- put the recommendation first
- weather is supporting evidence
- similar past day is the trust builder
- if a past-day photo exists, show it first to make the advice feel real

Primary blocks:

- hero with outfit recommendation
- compact weather strip
- similar day proof with photo-first fallback behavior
- record CTA

### 2. Record

Goal:

- make logging feel fast and tactile
- category choice should feel like outfit chips, not radio buttons

Primary blocks:

- today's summary
- 6 outfit cards
- feeling selector
- photo dropzone
- note field

### 3. Settings

Goal:

- reduce operational form feeling
- make location selection feel like setup, not data entry

Primary blocks:

- main location
- sub location
- current location helper

## Visual system

Base palette:

- ink: `#11243a`
- cloud: `#f7fbff`
- mist: `#e6f0f8`
- sky: `#4d8fcb`
- deep sky: `#1e5d96`
- fog: `#d7e5f1`

Typography mood:

- strong condensed-looking heading feel
- simple readable body
- large number typography for temperature

## Interaction tone

- one main answer only
- one main action per section
- soft transitions, no excessive animation

## Rebuild order

1. lock the visual system
2. rebuild the Today screen
3. rebuild Record
4. rebuild Settings
5. then wire the real app back in
