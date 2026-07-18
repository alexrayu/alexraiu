import puppeteer from 'puppeteer';
const url='https://alexraiu.ddev.site/articles/xdebug-breakpoints-silently-stopped-check-squatter-port-9003';
const b=await puppeteer.launch({headless:'new',args:['--no-sandbox','--ignore-certificate-errors']});
const p=await b.newPage();
await p.setViewport({width:1280,height:900});
await p.goto(url,{waitUntil:'networkidle0'});
const r=await p.evaluate(()=>{
  const de=document.documentElement;
  const pre=document.querySelector('.text-content pre');
  const cs=getComputedStyle(pre);
  const rect=pre.getBoundingClientRect();
  return {
    docScrollW:de.scrollWidth, docClientW:de.clientWidth,
    horizOverflow: de.scrollWidth>de.clientWidth,
    preClass:pre.className,
    preWidth:cs.width, preMarginLeft:cs.marginInlineStart, preOverflowX:cs.overflowX,
    preRight:Math.round(rect.right), preLeft:Math.round(rect.left), viewportW:window.innerWidth
  };
});
console.log(JSON.stringify(r,null,2));
await b.close();
