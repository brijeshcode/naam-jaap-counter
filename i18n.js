const I18N = {
  en: {
    appTitle:'Naam Jaap Counter', tapToCount:'Tap to Count', totalJaap:'Total Jaap',
    totalMalas:'Total Malas', todayJaap:"Today's Jaap", todayMalas:"Today's Malas",
    addPhysical:'Add Physical Counter/Mala Counts', addCount:'Add Count',
    resetToday:"Reset Today's Count", resetConfirm:"Reset today's count to zero? This cannot be undone.",
    login:'Login', register:'Register', username:'Username', password:'Password', logout:'Logout',
    today:'Today', total:'Total', jaap:'Jaap', malas:'Malas', selected:'Selected',
    language:'Language', numberFormat:'Number format', theme:'Theme',
    history:'History', weekly:'Weekly', monthly:'Monthly', yearly:'Yearly', refresh:'Refresh', install:'Install App',
    loggedElsewhere:'You were logged out because you signed in on another device.',
    notSynced:'Not synced', deviceActiveConfirm:'Logging in here will log you out from your other device. Continue?'
  },
  hi: {
    appTitle:'नाम जाप काउंटर', tapToCount:'गिनने के लिए टैप करें', totalJaap:'कुल जाप',
    totalMalas:'कुल माला', todayJaap:'आज का जाप', todayMalas:'आज की माला',
    addPhysical:'भौतिक काउंटर/माला गिनती जोड़ें', addCount:'गिनती जोड़ें',
    resetToday:'आज की गिनती रीसेट करें', resetConfirm:'आज की गिनती शून्य कर दें? यह पूर्ववत नहीं होगा।',
    login:'लॉगिन', register:'रजिस्टर', username:'उपयोगकर्ता नाम', password:'पासवर्ड', logout:'लॉगआउट',
    today:'आज', total:'कुल', jaap:'जाप', malas:'माला', selected:'चयनित',
    language:'भाषा', numberFormat:'संख्या प्रारूप', theme:'थीम',
    history:'इतिहास', weekly:'साप्ताहिक', monthly:'मासिक', yearly:'वार्षिक', refresh:'रिफ्रेश', install:'ऐप इंस्टॉल करें',
    loggedElsewhere:'आप लॉगआउट हो गए क्योंकि आपने दूसरे डिवाइस पर साइन इन किया।',
    notSynced:'सिंक नहीं हुआ', deviceActiveConfirm:'यहाँ लॉगिन करने पर आप अपने दूसरे डिवाइस से लॉगआउट हो जाएंगे। जारी रखें?'
  }
};
export default I18N;
if (typeof window !== 'undefined') window.NJ_I18N = I18N;
