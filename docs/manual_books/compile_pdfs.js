const { chromium } = require('/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/node_modules/playwright');
const path = require('path');
const fs = require('fs');

const MANUAL_DIR = __dirname;
const WEBSITE_DOCS_DIR = '/Users/lrmcorporation/Documents/Website/hrghrd/docs/manual_books';

const documents = [
  {
    html: 'MANUAL_BOOK_SUPER_ADMIN_LRM_CORP.html',
    pdf: 'MANUAL_BOOK_SUPER_ADMIN_LRM_CORP.pdf',
    title: 'Super Admin Manual Book'
  },
  {
    html: 'MANUAL_BOOK_MANAGER_CABANG_LRM_CORP.html',
    pdf: 'MANUAL_BOOK_MANAGER_CABANG_LRM_CORP.pdf',
    title: 'Manager Cabang Manual Book'
  },
  {
    html: 'MANUAL_BOOK_KARYAWAN_STAF_LRM_CORP.html',
    pdf: 'MANUAL_BOOK_KARYAWAN_STAF_LRM_CORP.pdf',
    title: 'Karyawan & Staf Manual Book'
  }
];

async function compileAll() {
  console.log('Launching headless Chromium compiler...');
  const browser = await chromium.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox']
  });

  const page = await browser.newPage();

  for (const doc of documents) {
    const htmlPath = path.join(MANUAL_DIR, doc.html);
    const pdfPath = path.join(MANUAL_DIR, doc.pdf);
    
    console.log(`\nCompiling [${doc.title}]...`);
    console.log(`Loading: file://${htmlPath}`);
    
    await page.goto(`file://${htmlPath}`, { waitUntil: 'networkidle' });
    
    // Ensure all images are fully loaded
    await page.evaluate(async () => {
      const selectors = Array.from(document.querySelectorAll('img'));
      await Promise.all(selectors.map(img => {
        if (img.complete) return;
        return new Promise((resolve, reject) => {
          img.addEventListener('load', resolve);
          img.addEventListener('error', resolve);
        });
      }));
    });
    
    await page.waitForTimeout(1000);

    console.log(`Generating PDF to: ${pdfPath}...`);
    // Use native CSS paged media footer via @page in style.css
    // Setting displayHeaderFooter to false avoids duplicate overlapping footer
    await page.pdf({
      path: pdfPath,
      format: 'A4',
      printBackground: true,
      displayHeaderFooter: false
    });

    const stat = fs.statSync(pdfPath);
    console.log(`Successfully generated ${doc.pdf} (${(stat.size / 1024 / 1024).toFixed(2)} MB)`);

    // Sync to Website repo docs as well
    if (fs.existsSync(WEBSITE_DOCS_DIR)) {
      const webTarget = path.join(WEBSITE_DOCS_DIR, doc.pdf);
      fs.copyFileSync(pdfPath, webTarget);
      console.log(`Synced to Website/hrghrd: ${webTarget}`);
    }
  }

  await browser.close();
  console.log('\n=== ALL 3 PDF MANUAL BOOKS COMPILED AND SYNCED SUCCESSFULLY! ===');
}

compileAll().catch(err => {
  console.error('Fatal compilation error:', err);
  process.exit(1);
});
