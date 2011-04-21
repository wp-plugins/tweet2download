function tweet2download_show_content(id, content) {
	var el = document.getElementById(id);
	jQuery(el).append(content);
	jQuery(el).show("slow");
}