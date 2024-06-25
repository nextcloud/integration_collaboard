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
const updatedLines = []
let msgid = ''
for (const line of poLines) {
	if (line.startsWith('msgid ')) {
		msgid = line
	} else if (line.startsWith('msgstr ""')) {
		updatedLines.push('msgstr ' + msgid.slice(6))
		continue
	}
	updatedLines.push(line)
}

// Save the updated content to a new .po file
writePoFile(updatedPoFilePath, updatedLines)

console.log('Updated file path:', updatedPoFilePath)
