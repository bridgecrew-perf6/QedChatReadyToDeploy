# Copyright (C) 2004-2018 Quod Erat Demonstrandum e.V. <webmaster@qed-verein.de>
#
# This file is part of QED-Chat.
#
# QED-Chat is free software: you can redistribute it and/or modify it
# under the terms of the GNU Affero General Public License as
# published by the Free Software Foundation, either version 3 of the
# License, or (at your option) any later version.
#
# QED-Chat is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU Affero General Public License for more details.
#
# You should have received a copy of the GNU Affero General Public
# License along with QED-Chat.  If not, see
# <http://www.gnu.org/licenses/>.

require 'jwt'

#This class provides methodes to interface with the chat-db.
class ChatBackend
	def initialize()
		@usernames = Hash.new
	end

	#Inserts a new post into db
	def createPost(name, message, channel, date, user_id, delay, bottag, public_id)
		if name.nil?
			name = ''
		end
		if message.nil?
			message = ''
		end

		sql = "INSERT INTO post (name, message, channel, date, user_id, delay, bottag, publicid) " +
			"VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
		chatDatabase {|db| db[sql, name, message, channel, date, user_id, delay, bottag, public_id].insert}
	end

	#Verifies the credentials
	# @return [Hash] Id and password if sucessful, nil if not
	def userAuthenticate(username, password)
		sql = "SELECT id, password FROM user WHERE username=? AND password=SHA1(CONCAT(username, ?))"
		chatDatabase {|db|
			row = db.fetch(sql, username, password).first
			return row.nil? ? nil : row.to_h
		}
	end

	#Verifies the cookie
	# @return [Integer] The uid if sucessful, nil if not
	def checkCookie(user_id, pwhash)
		begin
			token = JWT.decode(pwhash, $tokenSecret, true, 
				{ exp_leeway: $tokenExpirationLeeway, sub: user_id.to_s, verify_sub: true, algorithm: 'HS512'})
			return user_id
		rescue JWT::InvalidSubError => e
			writeToLog("Jwt subject missmatch! " + e.message)
		rescue JWT::ExpiredSignature => e
			writeToLog("Jwt signature expired! User: " + user_id + "\n" + e.message)
		rescue JWT::DecodeError => e
			
		rescue Exception => e
			writeException e
		end
	end

	def getCookie(user_id)
		exp = Time.now.to_i + $tokenExpirationSeconds
		payload = { exp: exp, sub: user_id}
		return JWT.encode payload, $tokenSecret, 'HS512'
	end

	#Gets the id of current post - offset
	def getCurrentId(channel, offset)
		sql = "SELECT id + 1 AS from_id FROM post WHERE channel = ? ORDER BY id DESC LIMIT ?, 1"
		chatDatabase {|db| 
			row = db.fetch(sql, channel, offset).first
			return row.nil? ? 0 : row[:from_id]
		}
	end

	#Gets the id of the last post generated by a certain user
	def getLastPostId(channel, uid, skip = 0)
		maxId = getCurrentId(channel, skip)
		sql = "SELECT MAX(id) AS from_id FROM post WHERE channel = ? AND user_id = ? AND id <= ?"
		chatDatabase {|db|
			row = db.fetch(sql, channel, uid, maxId).first
			return row.nil? ? 0: row[:from_id]
		}
	end

	SqlPostTemplate = "SELECT id, name, message, channel, DATE_FORMAT(date, '%Y-%m-%d %H:%i:%s') AS date, user_id, delay, bottag, publicid FROM post "
		
	#Gets all posts in a channel starting with id, orederd asc by id
	#Callback gets executed for each row
	def getPostsByStartId(channel, id, limit = 0, &callback)
		sql = SqlPostTemplate + "WHERE id >= ? AND channel = ? ORDER BY id"
		if limit == 0
			chatDatabase {|db| db.fetch(sql, id, channel, &callback)}
		else
			sql += " LIMIT ?"
			chatDatabase {|db| db.fetch(sql, id, channel, limit, &callback)}	
		end
	end
	
	def getPostsByIdInterval(channel, startId, endId, &callback)
		sql = SqlPostTemplate + "WHERE channel = ? AND id >= ? AND id <= ? ORDER BY id"
		chatDatabase {|db| db.fetch(sql, channel, startId, endId, &callback)}
	end

	def getPostsByDateInterval(channel, startDate, endDate, &callback)
		sql = SqlPostTemplate + "WHERE channel = ? AND date >= ? AND date <= ? ORDER BY id"
		chatDatabase {|db| db.fetch(sql, channel, startDate, endDate, &callback)}
	end

	def getPostsByStartDate(channel, startDate, &callback)
		sql = SqlPostTemplate + "WHERE channel = ? AND date >= ? ORDER BY id"
		chatDatabase {|db| db.fetch(sql, channel, startDate.strftime("%F %X"), &callback)}
	end
	
	#Use lazy loading to get usernames
	def getUsername(posting)
		if @usernames.has_key?(posting[:user_id])
			return @usernames[posting[:user_id]]
		else
			username = ''
			chatDatabase {|db| username = db[:user].select(:username).where(id: posting[:user_id]).first}
			if username.nil?
				username = '?'
			else
				username = username[:username]
			end
			@usernames[posting[:user_id]] = username
			return username
		end
	end

	def formatAsJson(posting)
		posting.each {|k, v| posting[k] = v.force_encoding('UTF-8') if v.class == String}
		if not posting[:publicid] then
			posting[:user_id] = nil
			posting[:username] = nil
		else
			posting[:username] = getUsername(posting)
		end
		posting.delete(:publicid)
		posting.merge!({'type' => 'post', 'color' => colorForName(posting[:name])})
		posting.to_json
	end

	def user?(user_id)
		sql = "SELECT id FROM user WHERE id=?"
		chatDatabase {|db|
			row = db.fetch(sql, user_id).first
			return !row.nil?
		}
	end

	private

	def colorForName(name)
		if name.nil?
			name = ''
		end

		md5 = Digest::MD5.new
		r = md5.hexdigest('a' + name + 'a')[-7..-1].to_i(16) % 156 + 100
		g = md5.hexdigest('b' + name + 'b')[-7..-1].to_i(16) % 156 + 100
		b = md5.hexdigest('c' + name + 'c')[-7..-1].to_i(16) % 156 + 100
		r.to_s(16) + g.to_s(16) + b.to_s(16)
	end

	#Create a new connection for each query to avoid connection timeouts
	def chatDatabase
		Sequel.connect($sqlConfig) {|db|
			db.run("SET NAMES UTF8mb4")
			yield(db)
		}
	end
end
