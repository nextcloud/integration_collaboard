/**
 * This is a script that takes the .pot file created by `translationtool.phar create-pot-files`
 * and returns the same file but with `msgstr` using `msgid` as default translation text.
 */

const fs = require('fs')

// Define the new file paths
const newPoFilePath = './translationfiles/templates/integration_collaboard.pot'
const updatedPoFilePath = './translationfiles/templates/updated_integration_collaboard.po'

// Function to read the content of the .po file
function readPoFile(filePath) {
	return fs.readFileSync(filePath, 'utf-8').split('\n')
}

// Function to write the updated content to a new .po file
function writePoFile(filePath, lines) {
	fs.writeFileSync(filePath, lines.join('\n'), 'utf-8')
}

// Read the content of the new .po file
const poLines = readPoFile(newPoFilePath)

// Process the lines to update msgstr with msgid if msgstr is empty
let updatedLines = []
let msgid = []
let isMultilineMsgid = false

poLines.forEach(line => {
	let pushCurrLine = true

	if (line.startsWith('msgid ""')) {
		isMultilineMsgid = true
		msgid.push(line)
	} else if (isMultilineMsgid && line.startsWith('"')) {
		msgid.push(line)
	} else if (line.startsWith('msgid ')) {
		msgid.push(line)
		isMultilineMsgid = false
	} else if (line.startsWith('msgstr ""')) {
		pushCurrLine = false

		if (msgid.length > 0) {
			if (msgid.length > 1) {
				updatedLines.push('msgstr ""')
				for (let i = 1; i < msgid.length; i++) {
					updatedLines.push(msgid[i])
				}
			} else {
				updatedLines.push('msgstr ' + msgid[0].slice(6))
			}

			msgid = []
		}
	} else {
		if (msgid.length > 0) {
			updatedLines = updatedLines.concat(msgid)
			msgid = []
			isMultilineMsgid = false
		}
	}

	pushCurrLine && updatedLines.push(line)
})

// Add remaining msgid to the updated lines if any
if (msgid.length > 0) {
	updatedLines = updatedLines.concat(msgid)
}

// Save the updated content to a new .po file
writePoFile(updatedPoFilePath, updatedLines)

console.log('Updated file path:', updatedPoFilePath)
