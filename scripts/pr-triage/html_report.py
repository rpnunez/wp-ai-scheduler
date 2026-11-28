#!/usr/bin/env python3
"""Self-contained, filterable HTML report for the PR triage data.

Used by classify.py, which writes pr_triage_report_<YYYY-MM-DD_HHMMSS>.html.
All CSS, JS, and data are inlined; the file works offline with no dependencies.
"""

import json
from datetime import datetime

# Fields embedded in the page (keeps the file small; body text is dropped).
FIELDS = [
	'number', 'title', 'url', 'author', 'author_is_bot', 'labels', 'draft', 'created_at', 'updated_at',
	'age_days', 'days_since_update', 'additions', 'deletions', 'changed_files', 'head_ref',
	'commits', 'commit_authors', 'commit_span_days', 'comments', 'comments_human', 'comments_bot',
	'reviews', 'reviews_human', 'reviews_bot', 'reviews_approved', 'reviews_changes_requested',
	'reviews_commented', 'reviewers', 'review_rounds', 'review_rounds_formal', 'review_rounds_human',
	'review_rounds_bot', 'review_rounds_comment', 'review_rounds_addressed', 'review_comments_agent',
	'threads', 'threads_resolved', 'threads_unresolved', 'threads_unresolved_active', 'threads_outdated',
	'ci_state', 'behind_by', 'ahead_by', 'merge_base_date', 'merge_clean', 'conflict_files',
	'conflict_files_count', 'conflict_hunks', 'pr_files_deleted_in_main', 'pr_files_renamed_in_main',
	'pr_files_changed_in_main', 'main_commits_touching_pr_files', 'closing_issues',
	'bucket', 'wave', 'action', 'effort_score', 'cost_score', 'mandatory', 'high_effort', 'ready',
	'heavy_cost', 'duplicate_of', 'overlaps', 'suggested_labels', 'rationale',
]

TEMPLATE = r'''<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>PR Triage - __REPO__ - __GENERATED__</title>
<style>
:root{--bg:#f6f7f9;--fg:#1d2330;--muted:#667085;--card:#fff;--line:#e3e6eb;--accent:#2f6fde;--hover:#f0f4fb;
--A:#1f8a4c;--B:#2f6fde;--C:#7a4cc2;--D:#c27a12;--E:#c2392f;--F:#5f6b7a}
@media (prefers-color-scheme:dark){:root{--bg:#12151b;--fg:#e4e7ec;--muted:#98a2b3;--card:#1b1f27;--line:#2c323d;--hover:#222936}}
*{box-sizing:border-box}
body{margin:0;font:14px/1.45 -apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:var(--bg);color:var(--fg)}
header{padding:18px 24px 8px}
h1{margin:0 0 4px;font-size:22px}
.sub{color:var(--muted);font-size:13px}
.cards{display:flex;flex-wrap:wrap;gap:10px;padding:10px 24px}
.card{background:var(--card);border:1px solid var(--line);border-left:5px solid var(--c);border-radius:8px;padding:8px 14px;cursor:pointer;min-width:150px;user-select:none}
.card.on{outline:2px solid var(--c)}
.card b{font-size:20px;display:block}
.card span{color:var(--muted);font-size:12px}
.controls{display:flex;flex-wrap:wrap;gap:8px;align-items:center;padding:8px 24px;position:sticky;top:0;background:var(--bg);z-index:5;border-bottom:1px solid var(--line)}
input[type=search]{padding:7px 10px;border:1px solid var(--line);border-radius:6px;min-width:260px;background:var(--card);color:var(--fg)}
button,select{padding:6px 10px;border:1px solid var(--line);border-radius:6px;background:var(--card);color:var(--fg);cursor:pointer;font:inherit}
button:hover{background:var(--hover)}
.dd{position:relative}
.dd .panel{display:none;position:absolute;top:110%;left:0;background:var(--card);border:1px solid var(--line);border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.18);padding:8px;min-width:240px;max-height:380px;overflow:auto;z-index:10}
.dd.open .panel{display:block}
.dd .panel label{display:flex;gap:6px;align-items:center;padding:3px 4px;border-radius:4px;cursor:pointer;white-space:nowrap}
.dd .panel label:hover{background:var(--hover)}
.dd .panel .tools{display:flex;gap:6px;margin-bottom:6px;flex-wrap:wrap}
.dd .panel .tools button{padding:3px 8px;font-size:12px}
.dd .panel input[type=search]{min-width:0;width:100%;margin-bottom:6px}
.cnt{color:var(--muted);font-size:12px;margin-left:auto}
.badge{display:inline-block;background:var(--accent);color:#fff;border-radius:9px;padding:0 6px;font-size:11px;margin-left:4px}
.chk{display:flex;gap:4px;align-items:center}
.wrap{padding:8px 24px 40px;overflow-x:auto}
table{border-collapse:collapse;width:100%;background:var(--card);border:1px solid var(--line);border-radius:8px}
th,td{padding:6px 8px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top}
th{position:sticky;top:49px;background:var(--card);cursor:pointer;white-space:nowrap;font-size:12px;color:var(--muted);z-index:4}
th.sorted{color:var(--fg)}
td.n{text-align:right;font-variant-numeric:tabular-nums}
tr.row{cursor:pointer}
tr.row:hover{background:var(--hover)}
tr.detail td{background:var(--bg);font-size:13px}
.bk{display:inline-block;color:#fff;border-radius:4px;padding:0 6px;font-weight:600;background:var(--c)}
.lbl{display:inline-block;border:1px solid var(--line);border-radius:10px;padding:0 7px;margin:1px 2px 1px 0;font-size:11px;white-space:nowrap}
.lbl.m{border-color:var(--A);color:var(--A)}
.bar{height:6px;border-radius:3px;background:var(--line);width:60px;display:inline-block;vertical-align:middle;margin-right:4px}
.bar i{display:block;height:100%;border-radius:3px}
.ok{color:var(--A)}.bad{color:var(--E)}
a{color:var(--accent);text-decoration:none}a:hover{text-decoration:underline}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:6px 18px}
.grid div b{color:var(--muted);font-weight:500}
ul.files{margin:4px 0 0;padding-left:18px;max-height:160px;overflow:auto;font-family:Consolas,monospace;font-size:12px}
.title{max-width:420px}
</style>
</head>
<body>
<header>
<h1>Open PR Triage &mdash; __REPO__</h1>
<div class="sub">Report generated <b>__GENERATED__</b> &middot; data collected __COLLECTED__ against <code>__BASE__</code> &middot; <span id="total"></span> open PRs &middot; read-only analysis, no GitHub changes made</div>
</header>
<div class="cards" id="cards"></div>
<div class="controls">
<input type="search" id="q" placeholder="Search #, title, author, branch, label, file...">
<div class="dd" id="dd-labels"><button type="button">Labels<span class="badge" hidden></span> &#9662;</button><div class="panel"></div></div>
<div class="dd" id="dd-bucket"><button type="button">Bucket<span class="badge" hidden></span> &#9662;</button><div class="panel"></div></div>
<div class="dd" id="dd-wave"><button type="button">Wave<span class="badge" hidden></span> &#9662;</button><div class="panel"></div></div>
<div class="dd" id="dd-merge"><button type="button">Merge state<span class="badge" hidden></span> &#9662;</button><div class="panel"></div></div>
<div class="dd" id="dd-author"><button type="button">Author<span class="badge" hidden></span> &#9662;</button><div class="panel"></div></div>
<label class="chk"><input type="checkbox" id="f-effort"> High effort</label>
<label class="chk"><input type="checkbox" id="f-unres"> Has active unresolved threads</label>
<button type="button" id="reset">Reset</button>
<button type="button" id="csv">Export CSV</button>
<span class="cnt" id="count"></span>
</div>
<div class="wrap"><table><thead><tr id="head"></tr></thead><tbody id="body"></tbody></table></div>
<script id="data" type="application/json">__DATA__</script>
<script>
(function(){
const D=JSON.parse(document.getElementById('data').textContent);
const PRS=D.prs, BUCKETS=D.buckets, MANDATORY=new Set(D.mandatory);
const $=s=>document.querySelector(s);
const esc=s=>String(s==null?'':s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const mergeState=p=>p.merge_clean?'Merges clean':(p.conflict_files_count>=D.heavy_conflicts?'Heavy conflicts':'Conflicts');
const waveName=p=>p.wave?('Wave '+p.wave):'No wave';
$('#total').textContent=PRS.length;

const COLS=[
 ['number','PR',p=>'<a href="'+esc(p.url)+'" target="_blank" rel="noopener" onclick="event.stopPropagation()">#'+p.number+'</a>',1],
 ['bucket','Bkt',p=>'<span class="bk" style="--c:var(--'+p.bucket+')" title="'+esc(BUCKETS[p.bucket])+'">'+p.bucket+'</span>'],
 ['title','Title',p=>'<div class="title">'+esc(p.title)+(p.draft?' <span class="lbl">draft</span>':'')+'</div><div>'+p.labels.map(l=>'<span class="lbl'+(MANDATORY.has(l)?' m':'')+'">'+esc(l)+'</span>').join('')+'</div>'],
 ['author','Author',p=>esc(p.author)],
 ['age_days','Age d',p=>p.age_days,1],
 ['commits','Commits',p=>p.commits,1],
 ['review_rounds','Rounds',p=>p.review_rounds,1],
 ['reviews','Reviews',p=>p.reviews,1],
 ['threads_resolved','Thr res/tot',p=>p.threads_resolved+'/'+p.threads+(p.threads_unresolved_active?' <span class="bad">('+p.threads_unresolved_active+')</span>':''),1],
 ['comments','Cmts',p=>p.comments,1],
 ['behind_by','Behind',p=>p.behind_by==null?'?':p.behind_by,1],
 ['conflict_files_count','Conflicts',p=>p.merge_clean?'<span class="ok">clean</span>':'<span class="bad">'+p.conflict_files_count+'f/'+p.conflict_hunks+'h</span>',1],
 ['effort_score','Effort',p=>bar(p.effort_score,'var(--C)'),1],
 ['cost_score','Cost',p=>bar(p.cost_score,'var(--E)'),1],
 ['action','Next action',p=>esc(p.action)],
];
function bar(v,c){return '<span class="bar"><i style="width:'+v+'%;background:'+c+'"></i></span>'+v}

let sortKey='cost_score', sortDir=1, open=new Set();
const head=$('#head');
COLS.forEach(([k,label])=>{const th=document.createElement('th');th.textContent=label;th.dataset.k=k;
 th.onclick=()=>{if(sortKey===k)sortDir=-sortDir;else{sortKey=k;sortDir=1}render()};head.appendChild(th)});

// Multi-select dropdowns
const filters={};
function dropdown(id,values,getter,opts){
 opts=opts||{};const dd=$('#'+id),panel=dd.querySelector('.panel'),badge=dd.querySelector('.badge');
 const sel=new Set();const st={sel,mode:'any',getter,multi:!!opts.multi};filters[id]=st;
 let html='';
 if(values.length>8)html+='<input type="search" placeholder="Filter list...">';
 html+='<div class="tools"><button data-a="all">All</button><button data-a="none">None</button>';
 if(opts.multi)html+='<select data-a="mode"><option value="any">Match any</option><option value="all">Match all</option></select>';
 html+='</div>';
 values.forEach(([v,label,n])=>{html+='<label data-v="'+esc(v)+'"><input type="checkbox" value="'+esc(v)+'"> '+esc(label)+' <span class="cnt">'+n+'</span></label>'});
 panel.innerHTML=html;
 const sync=()=>{badge.hidden=!sel.size;badge.textContent=sel.size;render()};
 panel.addEventListener('change',e=>{const t=e.target;
  if(t.dataset.a==='mode'){st.mode=t.value;sync();return}
  if(t.type==='checkbox'){t.checked?sel.add(t.value):sel.delete(t.value);sync()}});
 panel.addEventListener('click',e=>{const a=e.target.dataset.a;if(a!=='all'&&a!=='none')return;
  panel.querySelectorAll('label').forEach(l=>{if(l.style.display==='none')return;const c=l.querySelector('input');c.checked=a==='all';c.checked?sel.add(c.value):sel.delete(c.value)});sync()});
 const fs=panel.querySelector('input[type=search]');
 if(fs)fs.addEventListener('input',()=>{const q=fs.value.toLowerCase();panel.querySelectorAll('label').forEach(l=>l.style.display=l.dataset.v.toLowerCase().includes(q)?'':'none')});
 dd.querySelector('button').onclick=e=>{e.stopPropagation();document.querySelectorAll('.dd.open').forEach(o=>o!==dd&&o.classList.remove('open'));dd.classList.toggle('open')};
 panel.addEventListener('click',e=>e.stopPropagation());
 st.set=vals=>{sel.clear();vals.forEach(v=>sel.add(v));panel.querySelectorAll('label input').forEach(c=>c.checked=sel.has(c.value));badge.hidden=!sel.size;badge.textContent=sel.size};
}
document.addEventListener('click',()=>document.querySelectorAll('.dd.open').forEach(o=>o.classList.remove('open')));
function counts(fn){const m=new Map();PRS.forEach(p=>[].concat(fn(p)).forEach(v=>m.set(v,(m.get(v)||0)+1)));return m}
const labelCounts=counts(p=>p.labels.length?p.labels:['(no labels)']);
dropdown('dd-labels',[...labelCounts].sort((a,b)=>a[0].localeCompare(b[0])).map(([v,n])=>[v,v,n]),p=>p.labels.length?p.labels:['(no labels)'],{multi:true});
const bc=counts(p=>p.bucket);
dropdown('dd-bucket',Object.keys(BUCKETS).map(b=>[b,b+' - '+BUCKETS[b],bc.get(b)||0]),p=>[p.bucket]);
const wc=counts(waveName);
dropdown('dd-wave',['Wave 1','Wave 2','Wave 3','No wave'].map(w=>[w,w,wc.get(w)||0]),p=>[waveName(p)]);
const mc=counts(mergeState);
dropdown('dd-merge',['Merges clean','Conflicts','Heavy conflicts'].map(m=>[m,m,mc.get(m)||0]),p=>[mergeState(p)]);
const ac=counts(p=>p.author);
dropdown('dd-author',[...ac].sort((a,b)=>b[1]-a[1]).map(([v,n])=>[v,v,n]),p=>[p.author]);

// Bucket cards (shortcut for the bucket filter)
const cards=$('#cards');
Object.keys(BUCKETS).forEach(b=>{const c=document.createElement('div');c.className='card';c.style.setProperty('--c','var(--'+b+')');c.dataset.b=b;
 c.innerHTML='<b>'+(bc.get(b)||0)+'</b>'+b+'. '+esc(BUCKETS[b]);
 c.onclick=()=>{const s=filters['dd-bucket'].sel;const only=s.size===1&&s.has(b);filters['dd-bucket'].set(only?[]:[b]);render()};cards.appendChild(c)});

['#q','#f-effort','#f-unres'].forEach(s=>$(s).addEventListener('input',render));
$('#reset').onclick=()=>{$('#q').value='';$('#f-effort').checked=false;$('#f-unres').checked=false;Object.values(filters).forEach(f=>f.set([]));render()};

function haystack(p){return ['#'+p.number,p.title,p.author,p.head_ref,p.action,p.rationale].concat(p.labels,p.conflict_files||[]).join(' ').toLowerCase()}
PRS.forEach(p=>p._h=haystack(p));
function visible(){
 const q=$('#q').value.trim().toLowerCase().split(/\s+/).filter(Boolean);
 return PRS.filter(p=>{
  if(q.some(t=>!p._h.includes(t)))return false;
  if($('#f-effort').checked&&!p.high_effort)return false;
  if($('#f-unres').checked&&!p.threads_unresolved_active)return false;
  for(const f of Object.values(filters)){if(!f.sel.size)continue;const vals=f.getter(p);
   const ok=f.mode==='all'?[...f.sel].every(v=>vals.includes(v)):vals.some(v=>f.sel.has(v));if(!ok)return false}
  return true});
}
function cmp(a,b){let x=a[sortKey],y=b[sortKey];if(x==null)x=-1;if(y==null)y=-1;
 if(typeof x==='string')return sortDir*x.localeCompare(y);if(typeof x==='boolean'){x=+x;y=+y}return sortDir*(x-y)||(a.number-b.number)}
function list(arr){return arr&&arr.length?arr.map(esc).join(', '):'-'}
function prs(arr){return arr&&arr.length?arr.map(n=>'<a href="#" data-pr="'+n+'">#'+n+'</a>').join(', '):'-'}
function detail(p){
 const f=x=>'<ul class="files">'+x.map(v=>'<li>'+esc(v)+'</li>').join('')+'</ul>';
 return '<tr class="detail"><td colspan="'+COLS.length+'"><div class="grid">'+
 '<div><b>Why:</b> '+esc(p.rationale)+'</div>'+
 '<div><b>Suggested labels:</b> '+list(p.suggested_labels)+'</div>'+
 '<div><b>Duplicate of:</b> '+prs(p.duplicate_of)+' &middot; <b>Overlaps:</b> '+prs(p.overlaps)+'</div>'+
 '<div><b>Branch:</b> <code>'+esc(p.head_ref)+'</code></div>'+
 '<div><b>Created:</b> '+esc((p.created_at||'').slice(0,10))+' &middot; <b>Updated:</b> '+esc((p.updated_at||'').slice(0,10))+' ('+p.days_since_update+'d ago)</div>'+
 '<div><b>Size:</b> +'+p.additions+'/-'+p.deletions+' in '+p.changed_files+' files</div>'+
 '<div><b>Review rounds:</b> '+p.review_rounds+' (formal human '+p.review_rounds_human+', formal bot '+p.review_rounds_bot+', comment-only '+p.review_rounds_comment+'; '+p.review_rounds_addressed+' followed by new commits)</div>'+
 '<div><b>Reviews:</b> '+p.reviews_approved+' approved, '+p.reviews_changes_requested+' changes requested, '+p.reviews_commented+' commented; '+p.review_comments_agent+' review comments</div>'+
 '<div><b>Threads:</b> '+p.threads_resolved+' resolved, '+p.threads_unresolved+' unresolved ('+p.threads_unresolved_active+' active, '+p.threads_outdated+' outdated)</div>'+
 '<div><b>Comments:</b> '+p.comments_human+' human, '+p.comments_bot+' bot</div>'+
 '<div><b>Reviewers:</b> '+list(p.reviewers)+'</div>'+
 '<div><b>Commit authors:</b> '+list(p.commit_authors)+' &middot; span '+p.commit_span_days+'d</div>'+
 '<div><b>Behind/ahead:</b> '+p.behind_by+'/'+p.ahead_by+' &middot; merge base '+esc((p.merge_base_date||'').slice(0,10))+'</div>'+
 '<div><b>Main moved:</b> '+p.main_commits_touching_pr_files+' commits touched '+p.pr_files_changed_in_main+' of its files</div>'+
 '<div><b>CI:</b> '+esc(p.ci_state)+' &middot; <b>Closes:</b> '+list((p.closing_issues||[]).map(n=>'#'+n))+'</div>'+
 '</div>'+
 (p.conflict_files&&p.conflict_files.length?'<div style="margin-top:6px"><b>Conflicting files ('+p.conflict_files.length+'):</b>'+f(p.conflict_files)+'</div>':'')+
 (p.pr_files_deleted_in_main&&p.pr_files_deleted_in_main.length?'<div><b class="bad">Deleted in main:</b>'+f(p.pr_files_deleted_in_main)+'</div>':'')+
 (p.pr_files_renamed_in_main&&p.pr_files_renamed_in_main.length?'<div><b>Renamed in main:</b>'+f(p.pr_files_renamed_in_main)+'</div>':'')+
 '</td></tr>';
}
let current=[];
function render(){
 current=visible().sort(cmp);
 head.querySelectorAll('th').forEach(th=>{const on=th.dataset.k===sortKey;th.classList.toggle('sorted',on);
  th.textContent=COLS.find(c=>c[0]===th.dataset.k)[1]+(on?(sortDir>0?' \u25B2':' \u25BC'):'')});
 $('#body').innerHTML=current.map(p=>'<tr class="row" data-n="'+p.number+'">'+COLS.map(c=>'<td'+(c[3]?' class="n"':'')+'>'+c[2](p)+'</td>').join('')+'</tr>'+(open.has(p.number)?detail(p):'')).join('');
 $('#count').textContent='Showing '+current.length+' of '+PRS.length;
 const bs=filters['dd-bucket'].sel;cards.querySelectorAll('.card').forEach(c=>c.classList.toggle('on',bs.has(c.dataset.b)));
}
$('#body').addEventListener('click',e=>{
 const a=e.target.closest('a[data-pr]');
 if(a){e.preventDefault();const n=+a.dataset.pr;$('#q').value='#'+n;open.add(n);render();return}
 const tr=e.target.closest('tr.row');if(!tr)return;const n=+tr.dataset.n;open.has(n)?open.delete(n):open.add(n);render()});
$('#csv').onclick=()=>{
 const keys=['number','title','bucket','wave','action','labels','author','age_days','commits','review_rounds','reviews','threads_resolved','threads','threads_unresolved_active','comments','behind_by','merge_clean','conflict_files_count','conflict_hunks','effort_score','cost_score','suggested_labels','rationale','url'];
 const q=v=>'"'+String(Array.isArray(v)?v.join(';'):v==null?'':v).replace(/"/g,'""')+'"';
 const csv=[keys.join(',')].concat(current.map(p=>keys.map(k=>q(p[k])).join(','))).join('\r\n');
 const a=document.createElement('a');a.href=URL.createObjectURL(new Blob([csv],{type:'text/csv'}));a.download='pr-triage-filtered.csv';a.click()};
render();
})();
</script>
</body>
</html>
'''


def build_html(data, prs, buckets, mandatory, heavy_conflicts, generated):
	payload = {
		'buckets': buckets,
		'mandatory': sorted(mandatory),
		'heavy_conflicts': heavy_conflicts,
		'prs': [{k: p.get(k) for k in FIELDS} for p in prs],
	}
	blob = json.dumps(payload, separators=(',', ':')).replace('</', '<\\/')
	collected = data.get('generated_at', '')
	try:
		collected = datetime.fromisoformat(collected).astimezone().strftime('%Y-%m-%d %H:%M %Z')
	except ValueError:
		pass
	esc = lambda s: str(s).replace('&', '&amp;').replace('<', '&lt;').replace('>', '&gt;')
	return (TEMPLATE.replace('__REPO__', esc(data['repo'])).replace('__BASE__', esc(data['base']))
		.replace('__GENERATED__', esc(generated.strftime('%Y-%m-%d %H:%M:%S %Z').strip()))
		.replace('__COLLECTED__', esc(collected)).replace('__DATA__', blob))


def html_filename(generated):
	return 'pr_triage_report_%s.html' % generated.strftime('%Y-%m-%d_%H%M%S')
