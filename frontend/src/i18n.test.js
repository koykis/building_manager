import {describe,it,expect,vi} from 'vitest'
vi.stubGlobal('localStorage',{getItem:()=>null})
const {messages,i18n}=await import('./i18n')
describe('Bilingual interface',()=>{it('defaults to Greek and provides English for every label',()=>{expect(i18n.global.locale.value).toBe('el');expect(Object.keys(messages.el).sort()).toEqual(Object.keys(messages.en).sort());for(const value of Object.values(messages.el))expect(value.length).toBeGreaterThan(0)});it('labels boiler quantity as cubic metres in both languages',()=>{expect(messages.el.boiler).toContain('m³');expect(messages.en.boiler).toContain('m³')})})
