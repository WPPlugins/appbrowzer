var toggleMenu = function(){
    var m = document.getElementById('wporg-header-menu'),
        c = m.className;
	    m.className = c.match( ' active' ) ? c.replace( ' active', '' ) : c + ' active';
}