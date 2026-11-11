/* ARBNT stage 2: shared grid controls and bilingual labels. No backend or dependencies. */
(function(){
'use strict';
const dict={
'استكشف ARBNT':'Explore ARBNT','الرئيسية':'Home','المنشورات':'Posts','المجتمعات':'Communities','الملف الشخصي':'Profile','روابط مفيدة':'Useful links','أحدث المنشورات':'Recent posts','استكشف المجالات':'Explore topics','واجهة المرحلة الثانية — نموذج تجريبي.':'Stage two interface preview.',
'3 أعمدة':'3 columns','عمودان':'2 columns','عمود واحد':'1 column','تخطيط الصفحة':'Page layout',
'منشور تجريبي':'Sample post','الردود':'Replies','إضافة رد — قريباً':'Reply — coming soon',
'الذكاء الاصطناعي':'Artificial intelligence','كيف نستفيد من الوكلاء الأذكياء في تطوير المواقع؟':'How can AI agents improve web development?',
'يمكن للوكلاء المساعدة في كتابة الاختبارات، مراجعة الكود، وتوثيق المشاريع، مع إبقاء المراجعة والاعتماد بيد المطوّر.':'Agents can help with testing, code review and documentation, while developers retain final approval.',
'مثال توضيحي لتصميم صفحة منشور مستقل. لا توجد بيانات أعضاء أو ردود حقيقية حتى الآن.':'Example of a standalone post page. No real members or replies yet.',
'نموذج توضيحي · لم ينشره مستخدم حقيقي':'Sample only · Not published by a real member',
'لم يتم تفعيل الردود بعد. ستظهر هنا الردود المتسلسلة، التصويت، والردود المقبولة عند بناء المحرك.':'Replies are not enabled yet. Threaded replies, voting and accepted answers are planned.',
'الملف الشخصي':'Profile','تصور أولي لصفحة العضو قبل تفعيل التسجيل.':'An early profile preview before registration is enabled.',
'عضو تجريبي':'Sample member','ملف تجريبي — ليس حساباً مسجلاً':'Sample profile — not a registered account',
'نبذة المستخدم واهتماماته وروابطه ستظهر هنا.':'The member biography, interests and links will appear here.',
'المنشورات: —':'Posts: —','الردود: —':'Replies: —','المفضلة: —':'Favorites: —',
'النشاط والمشاركات':'Activity and contributions','تظهر هنا منشورات العضو وردوده ومساهماته عند تفعيل نظام الحسابات.':'Member posts, replies and contributions will appear once accounts are enabled.',
'المجتمعات والتصنيفات':'Communities and categories',
'المجتمعات المقترحة للتنظيم والتصفح؛ ليست مجتمعات منشأة بعد.':'Suggested communities for browsing; these are not live communities yet.',
'الوكلاء والأدوات والتطبيقات.':'Agents, tools and applications.','التطوير الحديث':'Modern development',
'الويب والبرمجة والأدوات.':'Web, programming and tools.','مفتوحة المصدر':'Open source',
'الشيفرات والتعاون والمشاريع.':'Code, collaboration and projects.','الأمن السيبراني':'Cybersecurity',
'الأمان والخصوصية والتوعية.':'Security, privacy and awareness.','التواصل الذكي':'Smart communication',
'المجتمعات والتجارب الرقمية.':'Communities and digital experiences.','التسويق الجديد':'Modern marketing',
'المحتوى والنمو والتحليلات.':'Content, growth and analytics.'
};
const root=document.documentElement;
const preview=document.querySelector('.arb-page');
function translate(l){if(!preview)return;preview.querySelectorAll('h1,h2,h3,p,a,button,span').forEach(el=>{if(el.children.length||el.dataset.ar)return;const t=el.textContent.trim();if(dict[t]){if(!el.dataset.originalAr)el.dataset.originalAr=t;el.textContent=l==='en'?dict[el.dataset.originalAr]||el.dataset.originalAr:el.dataset.originalAr}else if(el.dataset.originalAr){el.textContent=l==='en'?dict[el.dataset.originalAr]||el.dataset.originalAr:el.dataset.originalAr}});}
function sync(){const l=root.lang==='en'?'en':'ar';translate(l);document.querySelectorAll('[data-ar][data-en]').forEach(el=>{el.textContent=el.dataset[l]});}
const lang=document.getElementById('lang');if(lang)lang.addEventListener('click',()=>setTimeout(sync,0));
if(preview){const buttons=[...preview.querySelectorAll('button[data-layout]')];buttons.forEach(b=>{b.addEventListener('click',()=>{try{sessionStorage.setItem('arbnt-layout',b.dataset.layout)}catch(e){}})});try{const chosen=sessionStorage.getItem('arbnt-layout');const b=buttons.find(x=>x.dataset.layout===chosen);if(b)b.click()}catch(e){}sync()}
function gridSetup(grid,controls){if(!grid||!controls)return;const cols=controls.querySelector('[data-grid-cols]'),rows=controls.querySelector('[data-grid-rows]'),next=controls.querySelector('[data-grid-next]'),prev=controls.querySelector('[data-grid-prev]');if(!cols||!rows)return;let page=0;const items=[...grid.children];function render(){const count=Math.max(1,Number(cols.value)*Number(rows.value));const active=items.filter(x=>!x.dataset.filtered);const pages=Math.max(1,Math.ceil(active.length/count));page=Math.min(page,pages-1);items.forEach(el=>{el.hidden=!active.includes(el)||active.indexOf(el)<page*count||active.indexOf(el)>=(page+1)*count});grid.dataset.cols=cols.value;if(prev)prev.disabled=page===0;if(next){next.disabled=page>=pages-1;next.textContent=(root.lang==='en'?'Next':'التالي')+' ('+(page+1)+'/'+pages+')'}}cols.onchange=rows.onchange=()=>{page=0;render()};if(next)next.onclick=()=>{page++;render()};if(prev)prev.onclick=()=>{page--;render()};render();return {render,reset(){page=0;render()}}}
window.ARBNT=Object.assign(window.ARBNT||{},{gridSetup});
})();