/* -- (c) Aaron Schulz 2009 */

/* Every time you change this JS please bump $wgCheckUserStyleVersion in CheckUser.php */

/*
* This function calculates the common range of a list of
* IPs. It should be set to update on keyUp. 
*/
function updateCIDRresult() {
	var form = document.getElementById( 'mw-checkuser-cidrform' );
	if( !form ) return; // no JS form
	form.style.display = 'inline'; // unhide form (JS active)
	var iplist = document.getElementById( 'mw-checkuser-iplist' );
	if( !iplist ) return; // no JS form
	// Each line has one IP or range
	var ips = iplist.value.split("\n");
	var bin_prefix = 0;
	// Go through each IP in the list, get it's binary form, and track
	// the largest binary prefix among them
	for( i=0; i<ips.length; i++ ) {
		// ...in the spirit of block.js, call this "addy"
		var addy = ips[i];
		// Match the first IP in each list (ignore other garbage)
		var ipV4 = addy.match(/\b(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})(\/\d+)?\b/);
		var ipV6 = addy.match(/\b(:(:[0-9A-Fa-f]{1,4}){1,7}|[0-9A-Fa-f]{1,4}(:{1,2}[0-9A-Fa-f]{1,4}|::$){1,7})(\/\d+)?\b/);
		// Binary form
		var bin = new String( "" );
		// Convert the IP to binary form
		if( ipV4 ) {
			var ip = ipV4[1];
			var cidr = ipV4[2]; // CIDR, if it exists
			// Get each quad integer
			var blocs = ip.split('.');
			for( x=0; x<blocs.length; x++ ) {
				bloc = parseInt( blocs[x], 10 );
				if( bloc > 255 ) continue; // bad IP!
				bin_block = bloc.toString(2); // concat bin with binary form of bloc
				while( bin_block.length < 8 ) {
					bin_block = "0" + bin_block; // pad out as needed
				}
				bin += bin_block;
			}
			// Apply any valid CIDRs
			if( cidr ) {
				cidr = cidr.match( /\d+$/ )[0]; // get rid of slash
				if( cidr >= 16 ) bin = bin.substring(0,cidr); // truncate bin
			}
			// Init bin_prefix
			if( bin_prefix === 0 ) {
				bin_prefix = new String( bin );
			// Get largest common bin_prefix
			} else {
				for( x=0; x<bin_prefix.length; x++ ) {
					if( bin_prefix[x] != bin[x] ) {
						bin_prefix = bin_prefix.substring(0,x); // shorten bin_prefix
						break;
					}
				}
			}
			// Build the IP in CIDR form
			var prefix_cidr = bin_prefix.length;
			// CIDR too small?
			if( prefix_cidr < 16 ) {
				document.getElementById( 'mw-checkuser-ipres' ).value = "!";
				document.getElementById( 'mw-checkuser-ipnote' ).innerHTML = '';
				return; // too big
			}
			var prefix = new String( "" );
			// First bloc (/8)
			var bloc = 0;
			for( x=0; x<=7; x++ ) {
				bloc += parseInt(bin_prefix[x],10)*Math.pow(2,7-x);
			}
			prefix += bloc + '.';
			// Second bloc (/16)
			var bloc = 0;
			for( x=8; x<=15; x++ ) {
				bloc += parseInt(bin_prefix[x],10)*Math.pow(2,15-x);
			}
			prefix += bloc + '.';
			// Third bloc (/24)
			var bloc = 0;
			for( x=16; x<=23; x++ ) {
				if( bin_prefix[x] == undefined ) break;
				bloc += parseInt(bin_prefix[x],10)*Math.pow(2,23-x);
			}
			prefix += bloc + '.';
			// First bloc (/32)
			var bloc = 0;
			for( x=24; x<=31; x++ ) {
				if( bin_prefix[x] == undefined ) break;
				bloc += parseInt(bin_prefix[x],10)*Math.pow(2,31-x);
			}
			prefix += bloc;
			document.getElementById( 'mw-checkuser-ipres' ).value = prefix + '/' + prefix_cidr;
			// Get IPs affected
			ip_count = Math.pow(2,32-prefix_cidr);
			document.getElementById( 'mw-checkuser-ipnote' ).innerHTML = '&nbsp;~' + ip_count;
		}
		/*
		TODO: IPv6
		} else if( isIpV6 ) {
			var ip = ipV6[1];
			var cidr = ipV6[2];
			// Get each quad integer
			var blocs = ip.split(':');
			for( x=0; x<blocs.length; x++ ) {
				if( blocs[x] > "ffff" ) continue; // bad IP!
			}
		}
		*/
	}
	
}
addOnloadHook( updateCIDRresult );
