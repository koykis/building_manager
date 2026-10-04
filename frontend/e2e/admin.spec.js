import {test,expect} from '@playwright/test'
import fs from 'node:fs'
const credentials=JSON.parse(fs.readFileSync(new URL('../../.local/admin-credentials.json',import.meta.url),'utf8'))
test('administrator login, bilingual reports, source review and private access',async({page})=>{
 const errors=[];page.on('pageerror',e=>errors.push(e.message))
 await page.goto('/');await expect(page.getByRole('heading',{name:'Σύνδεση'})).toBeVisible()
 await page.getByLabel('Email',{exact:true}).fill(credentials.email);await page.getByLabel('Κωδικός πρόσβασης',{exact:true}).fill(credentials.password);await page.getByRole('button',{name:'Σύνδεση',exact:true}).click()
 await expect(page.getByRole('heading',{name:'Επισκόπηση',exact:true})).toBeVisible();await expect(page.getByText('13 / 13',{exact:false})).toBeVisible()
 await page.getByRole('combobox',{name:'Language / Γλώσσα'}).selectOption('en');await expect(page.getByRole('heading',{name:'Overview',exact:true})).toBeVisible()
 await page.getByRole('button',{name:'Monthly statements',exact:true}).click();await expect(page.locator('tbody tr')).toHaveCount(13)
 await page.locator('tbody tr').first().getByRole('button',{name:'Review',exact:true}).click();await expect(page.getByRole('heading',{name:/2026-08/})).toBeVisible()
 await page.getByRole('button',{name:'Apartment allocations',exact:true}).click();await expect(page.locator('tbody tr')).toHaveCount(9);await expect(page.getByRole('columnheader',{name:'Boiler water (m³)'})).toBeVisible()
 await page.getByRole('button',{name:'Original source',exact:true}).click();await expect(page.locator('.source-image')).toBeVisible();await expect.poll(()=>page.locator('.source-image').evaluate(img=>img.naturalWidth)).toBeGreaterThan(0)
 await page.screenshot({path:'../.local/statement-desktop.png',fullPage:true})
 await page.setViewportSize({width:390,height:844});await page.getByRole('button',{name:'Overview',exact:true}).click();await expect(page.getByRole('heading',{name:'Overview',exact:true})).toBeVisible();await expect(page.getByText('13 / 13',{exact:false})).toBeVisible();await page.screenshot({path:'../.local/dashboard-mobile.png',fullPage:true})
 await page.getByRole('button',{name:'Sign out',exact:true}).click();await expect(page.getByRole('heading',{name:'Sign in',exact:true})).toBeVisible()
 const response=await page.request.get('/api/v1/admin/statements');expect(response.status()).toBe(401);expect(errors).toEqual([])
})
test('draft revision preserves publication and can be edited and removed',async({page})=>{
 await page.goto('/');await page.getByLabel('Email',{exact:true}).fill(credentials.email);await page.getByLabel('Κωδικός πρόσβασης',{exact:true}).fill(credentials.password);await page.getByRole('button',{name:'Σύνδεση',exact:true}).click();await expect(page.getByRole('heading',{name:'Επισκόπηση',exact:true})).toBeVisible();await page.getByRole('combobox',{name:'Language / Γλώσσα'}).selectOption('en')
 await page.getByRole('button',{name:'Monthly statements',exact:true}).click();await page.locator('tbody tr').first().getByRole('button',{name:'Review',exact:true}).click();await page.getByRole('button',{name:'Create revision',exact:true}).click();await expect(page.getByText('Draft',{exact:true})).toBeVisible();await page.getByLabel('Notes',{exact:true}).fill('Synthetic browser check; this draft will be deleted.');await page.getByRole('button',{name:'Save',exact:true}).click();await expect(page.getByRole('status')).toHaveText('Saved');await page.getByRole('button',{name:'Review',exact:true}).first().click();page.once('dialog',dialog=>dialog.accept());await page.getByRole('button',{name:'Delete draft',exact:true}).click();await expect(page.locator('tbody tr')).toHaveCount(13)
})
