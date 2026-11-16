import { describe, expect, it } from 'vitest'
import { SWIPE, flick, swipeSettings, wheelSteps } from './swipe.js'

const wheel = (deltaX, deltaY = 0) => {
  const event = new WheelEvent('wheel', { deltaX, deltaY, cancelable: true })
  return event
}

describe('swipe', () => {
  it('pages past 15% of the width, slowly; short of it, it stays', () => {
    // 300 wide, a second long: no flick, the length decides.
    expect(flick(-44, 1000, 300)).toBe(0)
    expect(flick(-46, 1000, 300)).toBe(1)
    expect(flick(46, 1000, 300)).toBe(-1)
    expect(flick(0, 1000, 300)).toBe(0)
  })

  it('pages by a flick faster than 0.4 px/ms, however short', () => {
    expect(flick(-20, 40, 300)).toBe(1)
    expect(flick(20, 40, 300)).toBe(-1)
    expect(flick(-20, 100, 300)).toBe(0)
  })

  it('takes the settings the server sent over the defaults, and nothing that is no number', () => {
    expect(swipeSettings()).toEqual(SWIPE)
    expect(swipeSettings({ threshold: 0.3, velocity: 'fast', wheel: -1, pause: null })).toEqual({
      ...SWIPE,
      threshold: 0.3,
    })
    // A threshold of 30%: a slow drag of 25% stays.
    expect(flick(-75, 1000, 300, swipeSettings({ threshold: 0.3 }))).toBe(0)
  })

  it('turns a touchpad gesture into one step once it adds up to 60px, then waits it out', () => {
    let time = 0
    const steps = []
    const listen = wheelSteps(
      (step) => steps.push(step),
      SWIPE,
      () => time,
    )

    // Sideways by 25, 25: not yet a step.
    for (const delta of [25, 25]) {
      time += 16
      listen(wheel(delta))
    }
    expect(steps).toEqual([])

    time += 16
    const third = wheel(25)
    listen(third)
    expect(steps).toEqual([1])
    expect(third.defaultPrevented).toBe(true)

    // The momentum that follows is the same gesture: no second step, however much of it.
    for (let at = 0; at < 20; at++) {
      time += 16
      listen(wheel(30))
    }
    expect(steps).toEqual([1])

    // 400 ms of quiet: the next gesture is a step of its own, back this time.
    time += 450
    listen(wheel(-40))
    time += 16
    listen(wheel(-40))
    expect(steps).toEqual([1, -1])
  })

  it('leaves a wheel that goes more down than sideways to the page', () => {
    const steps = []
    const listen = wheelSteps(
      (step) => steps.push(step),
      SWIPE,
      () => 0,
    )
    const down = wheel(50, 120)
    listen(down)
    listen(wheel(50, 120))
    expect(steps).toEqual([])
    expect(down.defaultPrevented).toBe(false)
  })

  it('leaves a gesture it cannot page untouched, but keeps one it took to its end', () => {
    let time = 0
    let edge = false
    const steps = []
    const listen = wheelSteps(
      (step) => {
        steps.push(step)
        edge = true
      },
      SWIPE,
      () => time,
      (direction) => !(edge && direction > 0),
    )

    time += 16
    const first = wheel(70)
    expect(listen(first)).toBe(true)
    expect(steps).toEqual([1])
    // At the edge now, but the momentum is this gesture's: still taken.
    time += 16
    const momentum = wheel(40)
    expect(listen(momentum)).toBe(true)
    expect(momentum.defaultPrevented).toBe(true)

    // A new gesture on past the edge: not this one's, not even prevented.
    time += 500
    const past = wheel(70)
    expect(listen(past)).toBe(false)
    expect(past.defaultPrevented).toBe(false)
    expect(steps).toEqual([1])
    // Back from the edge is.
    time += 500
    listen(wheel(-70))
    expect(steps).toEqual([1, -1])
  })

  it('forgets a sideways nudge after a pause', () => {
    let time = 0
    const steps = []
    const listen = wheelSteps(
      (step) => steps.push(step),
      SWIPE,
      () => time,
    )
    listen(wheel(40))
    time += 500
    listen(wheel(40))
    expect(steps).toEqual([])
  })
})
