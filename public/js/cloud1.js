$(function() {

var cloudOptions = {
	autoResize: true,
	delay: 0
};

var doRun = false;

$('#cloud1').jQCloud( [{text:'Politicator!',weight:10},{text:'What ARE they saying?',weight:5},{text:'Twitter!',weight:4},{text:' ',weight:1}], cloudOptions);

(function getHashtags() {
	if ( !doRun ) {
		doRun = true;
		setTimeout(getHashtags, 2700);
		return;
	}

	(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
	(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
	m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
	})(window,document,'script','https://www.google-analytics.com/analytics.js','ga');
	ga('create', 'UA-76466106-1', 'auto');
	ga('send', 'pageview');

	$.ajax({
		url: '/tag-cloud',
		dataType: 'json',
		success: function(data) {
			$('#cloud1').jQCloud('update', data);
			$('[title!=""]').qtip({ style: { classes: 'qtip-rounded' }, show: {solo: true} });//.reposition(true);
		},
		complete: function() {
			// Schedule the next request when the current one's complete
			setTimeout(getHashtags, 3500);
		}
	});
})();

});

function ToTwitter(hashtags)
{
	window.open('https://twitter.com/search?f=tweets&vertical=news&q=%23' + hashtags.join('%7c%23'), '_blank');
};
